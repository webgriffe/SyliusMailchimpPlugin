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
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;

final class CartSubscriber implements EventSubscriberInterface
{
    /**
     * Carts whose ID was not yet assigned when the event fired (new, un-flushed entities).
     * These are deferred to kernel.response, after Doctrine has persisted them.
     * Take a look at https://github.com/Sylius/Sylius/issues/19016 for more context.
     *
     * @var list<OrderInterface>
     */
    private array $pendingCarts = [];

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
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
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

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->pendingCarts === []) {
            return;
        }

        $carts = $this->pendingCarts;
        $this->pendingCarts = [];

        foreach ($carts as $cart) {
            if ($cart->getId() === null) {
                $this->logger->warning('[Mailchimp] CartSubscriber: deferred cart still has no ID after flush, skipping.');

                continue;
            }

            $this->logger->debug('[Mailchimp] CartSubscriber: deferred enqueue for order #{id} (post-flush).', ['id' => $cart->getId()]);
            $this->cartEnqueuer->enqueue($cart);
        }
    }

    private function enqueueCart(OrderInterface $cart): void
    {
        if ($cart->getId() !== null) {
            $this->cartEnqueuer->enqueue($cart);

            return;
        }

        // Cart has no ID yet — the DB flush has not happened. Buffer it and retry in kernel.response.
        $this->pendingCarts[] = $cart;
        $this->logger->debug('[Mailchimp] CartSubscriber: cart has no ID yet, deferring to kernel.response.');
    }
}
