<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;

final class CartSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartEnqueuerInterface $cartEnqueuer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            SyliusCartEvents::CART_CHANGE => 'onCartChange',
            SyliusCartEvents::CART_ITEM_ADD => 'onCartItemAdd',
            SyliusCartEvents::CART_ITEM_REMOVE => ['onCartItemRemove', 10],
            SyliusCartEvents::CART_CLEAR => 'onCartClear',
        ];
    }

    public function onCartChange(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof OrderInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] CartSubscriber: onCartChange for order #{id}.', ['id' => $subject->getId()]);
        $this->enqueueCart($subject);
    }

    public function onCartItemAdd(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof AddToCartCommandInterface) {
            return;
        }

        $order = $subject->getCart();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] CartSubscriber: onCartItemAdd for order #{id}.', ['id' => $order->getId()]);
        $this->enqueueCart($order);
    }

    public function onCartItemRemove(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof OrderItemInterface) {
            return;
        }

        $order = $subject->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] CartSubscriber: onCartItemRemove for order #{id}.', ['id' => $order->getId()]);
        $this->enqueueCart($order);
    }

    public function onCartClear(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof OrderInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] CartSubscriber: onCartClear for order #{id}.', ['id' => $subject->getId()]);
        $this->cartEnqueuer->enqueueRemoval($subject);
    }

    private function enqueueCart(OrderInterface $cart): void
    {
        if ($cart->getId() === null) {
            $this->logger->debug('[Mailchimp] CartSubscriber: cart has no ID yet (new cart), skipping real-time sync — will be picked up by cron.');

            return;
        }

        $this->cartEnqueuer->enqueue($cart);
    }
}
