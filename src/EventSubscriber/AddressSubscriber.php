<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;

final class AddressSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MemberEnqueuerInterface $memberEnqueuer,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.address.post_create' => 'onAddressChange',
            'sylius.address.post_update' => 'onAddressChange',
            'sylius.address.post_delete' => 'onAddressChange',
        ];
    }

    public function onAddressChange(GenericEvent $event): void
    {
        $address = $event->getSubject();
        if (!$address instanceof AddressInterface) {
            return;
        }

        $customer = $address->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $this->memberEnqueuer->enqueue($customer);
    }
}
