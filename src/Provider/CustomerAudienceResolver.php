<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;

final class CustomerAudienceResolver implements CustomerAudienceResolverInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function resolveAudienceId(CustomerInterface $customer): ?string
    {
        $channel = $this->resolveChannel($customer);
        if ($channel === null) {
            $this->logger->info('[Mailchimp] No channel context and no completed order for customer #{id}, cannot resolve the audience.', [
                'id' => $customer->getId(),
            ]);

            return null;
        }

        try {
            return $this->audienceProvider->getAudience($channel)->id;
        } catch (AudienceNotFoundException $e) {
            $this->logger->warning('[Mailchimp] Could not resolve audience for customer #{id}: {msg}', [
                'id' => $customer->getId(),
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveChannel(CustomerInterface $customer): ?ChannelInterface
    {
        try {
            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();

            return $channel;
        } catch (ChannelNotFoundException) {
        }

        /** @var list<OrderInterface> $orders */
        $orders = $this->orderRepository->findBy(
            ['customer' => $customer, 'checkoutState' => OrderCheckoutStates::STATE_COMPLETED],
            ['checkoutCompletedAt' => 'DESC'],
            1,
        );
        $channel = ($orders[0] ?? null)?->getChannel();

        return $channel instanceof ChannelInterface ? $channel : null;
    }
}
