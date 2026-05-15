<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CustomerSubscriber;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;

interface TestMailchimpCustomerInterface extends CustomerInterface, MailchimpAwareInterface
{
}

final class CustomerSubscriberTest extends TestCase
{
    private MockObject&MemberEnqueuerInterface $memberEnqueuer;

    private MockObject&AudienceContextInterface $audienceContext;

    private MockObject&MessageBusInterface $messageBus;

    private MockObject&EntityManagerInterface $entityManager;

    private MockObject&UnitOfWork $unitOfWork;

    private CustomerSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->memberEnqueuer = $this->createMock(MemberEnqueuerInterface::class);
        $this->audienceContext = $this->createMock(AudienceContextInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);

        $this->entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->subscriber = new CustomerSubscriber(
            $this->memberEnqueuer,
            $this->audienceContext,
            $this->messageBus,
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function test_subscribes_to_correct_events(): void
    {
        $events = CustomerSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('sylius.customer.post_register', $events);
        $this->assertArrayHasKey('sylius.customer.pre_update', $events);
        $this->assertArrayHasKey('sylius.customer.post_update', $events);
    }

    public function test_enqueues_customer_on_post_register_when_subscribed_to_newsletter(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);
        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);

        $this->subscriber->onCustomerPostRegister(new GenericEvent($customer));
    }

    public function test_skips_enqueue_on_post_register_when_not_subscribed_to_newsletter(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(false);
        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $this->subscriber->onCustomerPostRegister(new GenericEvent($customer));
    }

    public function test_ignores_non_customer_on_post_register(): void
    {
        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $this->subscriber->onCustomerPostRegister(new GenericEvent(new \stdClass()));
    }

    public function test_enqueues_update_when_no_email_change(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([]);

        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_skips_enqueue_on_post_update_when_not_subscribed_to_newsletter(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([]);

        $this->memberEnqueuer->expects($this->never())->method('enqueue');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_dispatches_remove_and_create_when_email_changed(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('new@example.com');
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', 'new@example.com'],
        ]);

        $this->audienceContext->method('getAudienceId')->willReturn('list-123');
        $this->messageBus->method('dispatch')->willReturnCallback(
            static fn (object $msg) => new Envelope($msg),
        );

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched): Envelope {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));

        $this->assertInstanceOf(MemberRemove::class, $dispatched[0]);
        $this->assertInstanceOf(MemberCreate::class, $dispatched[1]);
    }

    public function test_does_not_dispatch_create_when_new_email_is_empty(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('');
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', ''],
        ]);

        $this->audienceContext->method('getAudienceId')->willReturn('list-123');
        $this->messageBus->method('dispatch')->willReturnCallback(
            static fn (object $msg) => new Envelope($msg),
        );

        $dispatched = [];
        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched): Envelope {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));

        $this->assertInstanceOf(MemberRemove::class, $dispatched[0]);
    }

    public function test_does_not_dispatch_create_when_email_changed_but_not_subscribed_to_newsletter(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('new@example.com');
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', 'new@example.com'],
        ]);

        $this->audienceContext->method('getAudienceId')->willReturn('list-123');

        $dispatched = [];
        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched): Envelope {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(MemberRemove::class, $dispatched[0]);
    }

    public function test_logs_warning_when_audience_not_found_on_email_change(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('new@example.com');

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', 'new@example.com'],
        ]);

        $this->audienceContext->method('getAudienceId')->willThrowException(
            new AudienceNotFoundException('No audience'),
        );

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_ignores_non_integer_id_on_pre_update(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(null);

        $this->unitOfWork->expects($this->never())->method('getEntityChangeSet');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
    }
}
