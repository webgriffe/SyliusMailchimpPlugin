<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CustomerSubscriber;

final class CustomerSubscriberTest extends TestCase
{
    use ReflectionIdTrait;

    private MockObject&MemberEnqueuerInterface $memberEnqueuer;

    private MockObject&EntityManagerInterface $entityManager;

    private MockObject&UnitOfWork $unitOfWork;

    private CustomerSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->memberEnqueuer = $this->createMock(MemberEnqueuerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);

        $this->entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->subscriber = new CustomerSubscriber(
            $this->memberEnqueuer,
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

    public function test_enqueues_customer_on_post_register(): void
    {
        $customer = new Customer();
        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);

        $this->subscriber->onCustomerPostRegister(new GenericEvent($customer));
    }

    public function test_ignores_non_customer_on_post_register(): void
    {
        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $this->subscriber->onCustomerPostRegister(new GenericEvent(new \stdClass()));
    }

    public function test_enqueues_update_when_no_email_change(): void
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 42);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([]);

        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_dispatches_remove_and_create_when_email_changed(): void
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 42);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', 'new@example.com'],
        ]);

        $this->memberEnqueuer->expects($this->once())
            ->method('enqueueEmailChange')
            ->with($customer, 'old@example.com');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_does_not_dispatch_create_when_new_email_is_empty(): void
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 42);

        $this->unitOfWork->method('getEntityChangeSet')->willReturn([
            'email' => ['old@example.com', ''],
        ]);

        $this->memberEnqueuer->expects($this->once())
            ->method('enqueueEmailChange')
            ->with($customer, 'old@example.com');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
        $this->subscriber->onCustomerPostUpdate(new GenericEvent($customer));
    }

    public function test_ignores_non_integer_id_on_pre_update(): void
    {
        $customer = new Customer();

        $this->unitOfWork->expects($this->never())->method('getEntityChangeSet');

        $this->subscriber->onCustomerPreUpdate(new GenericEvent($customer));
    }
}
