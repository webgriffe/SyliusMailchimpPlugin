<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\EcommerceCustomer;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Message\EcommerceCustomer\EcommerceCustomerEmailChange;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

/**
 * Mailchimp forbids changing the email address of an existing ecommerce customer, so on
 * email change the customer is removed from every configured store together with its open
 * carts; the next cart/order sync recreates everything with the same id and the new email.
 */
#[AsMessageHandler]
final class EcommerceCustomerEmailChangeHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EcommerceCustomerEmailChange $message): void
    {
        $customer = $this->customerRepository->find($message->customerId);
        if (!$customer instanceof CustomerInterface) {
            $this->logger->warning('[Mailchimp] Customer #{id} not found, skipping EcommerceCustomerEmailChange.', ['id' => $message->customerId]);

            return;
        }

        foreach ($this->channelRepository->findAll() as $channel) {
            if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
                continue;
            }

            try {
                $audience = $this->audienceProvider->getAudience($channel);
            } catch (AudienceNotFoundException $e) {
                $this->logger->debug('[Mailchimp] No audience for channel #{id}, skipping EcommerceCustomerEmailChange: {msg}', [
                    'id' => $channel->getId(),
                    'msg' => $e->getMessage(),
                ]);

                continue;
            }

            try {
                $storeId = $this->storeIdentifierResolver->resolve($audience);
                // Carts must go first: Mailchimp refuses to delete a customer that still has
                // associated orders/carts.
                $this->removeOpenCarts($customer, $channel, $storeId);
                $this->mailchimpClient->removeEcommerceCustomer($storeId, (string) $message->customerId);
                $this->logger->info('[Mailchimp] Ecommerce customer #{id} removed from store {store} after email change.', [
                    'id' => $message->customerId,
                    'store' => $storeId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[Mailchimp] Failed to remove ecommerce customer #{id} from channel #{channel}: {msg}', [
                    'id' => $message->customerId,
                    'channel' => $channel->getId(),
                    'msg' => $e->getMessage(),
                ]);
                if (!MailchimpErrorClassifier::isPermanent($e)) {
                    // DELETE calls are idempotent, retrying the whole message is safe.
                    throw $e;
                }
            }
        }
    }

    private function removeOpenCarts(CustomerInterface $customer, ChannelInterface $channel, string $storeId): void
    {
        $carts = $this->orderRepository->findBy([
            'customer' => $customer,
            'channel' => $channel,
            'state' => OrderInterface::STATE_CART,
        ]);

        $needsFlush = false;
        foreach ($carts as $cart) {
            if (!$cart instanceof MailchimpOrderAwareInterface) {
                continue;
            }

            $mailchimpCartId = $cart->getMailchimpCartId();
            if ($mailchimpCartId === null || $mailchimpCartId === '') {
                continue;
            }

            $this->mailchimpClient->removeCart($storeId, $mailchimpCartId);
            $cart->setMailchimpCartId(null);
            $needsFlush = true;
        }

        if ($needsFlush) {
            $this->entityManager->flush();
        }
    }
}
