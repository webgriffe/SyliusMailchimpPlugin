<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartEnqueuerInterface $cartEnqueuer,
        private readonly OrderEnqueuerInterface $orderEnqueuer,
        private readonly bool $sendUnpaidOrdersAsCarts,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.order.post_update' => 'onOrderPostUpdate',
            'sylius.order.post_complete' => 'onOrderPostComplete',
        ];
    }

    public function onOrderPostUpdate(GenericEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $state = $order->getState();
        $this->logger->debug('[Mailchimp] onOrderPostUpdate: order #{id} state={state}.', [
            'id' => $order->getId(),
            'state' => $state,
        ]);

        if ($this->sendUnpaidOrdersAsCarts && $state === OrderInterface::STATE_NEW) {
            $this->cartEnqueuer->enqueue($order);
        }
    }

    public function onOrderPostComplete(GenericEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] onOrderPostComplete: order #{id}.', ['id' => $order->getId()]);
        $this->orderEnqueuer->enqueue($order, isInRealTime: true);
    }
}
