<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\AddressSubscriber;

final class AddressSubscriberTest extends TestCase
{
    private MockObject&MemberEnqueuerInterface $memberEnqueuer;

    private AddressSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->memberEnqueuer = $this->createMock(MemberEnqueuerInterface::class);
        $this->subscriber = new AddressSubscriber($this->memberEnqueuer);
    }

    public function test_subscribes_to_correct_events(): void
    {
        $events = AddressSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('sylius.address.post_create', $events);
        $this->assertArrayHasKey('sylius.address.post_update', $events);
        $this->assertArrayHasKey('sylius.address.post_delete', $events);
    }

    public function test_enqueues_the_owning_customer_on_address_change(): void
    {
        $customer = new Customer();
        $address = new Address();
        $address->setCustomer($customer);

        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);

        $this->subscriber->onAddressChange(new GenericEvent($address));
    }

    public function test_ignores_address_without_a_customer(): void
    {
        $address = new Address();

        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $this->subscriber->onAddressChange(new GenericEvent($address));
    }

    public function test_ignores_non_address_subjects(): void
    {
        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $this->subscriber->onAddressChange(new GenericEvent(new \stdClass()));
    }
}
