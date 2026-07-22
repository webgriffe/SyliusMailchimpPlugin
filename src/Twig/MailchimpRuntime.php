<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\ParsedError;

final class MailchimpRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MailchimpCustomerRepositoryInterface $customerRepository,
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
    ) {
    }

    /** @return array{label: string, color: string} */
    public function getSyncBadge(MailchimpAwareInterface $resource): array
    {
        if ($resource->getMailchimpError() !== null) {
            return ['label' => 'error', 'color' => 'red'];
        }

        if ($resource->getMailchimpId() !== null) {
            return ['label' => 'synced', 'color' => 'green'];
        }

        return ['label' => 'never', 'color' => 'grey'];
    }

    /** @psalm-suppress UnusedParam The $audienceId parameter is reserved for future use (audience-specific URLs) */
    public function getMemberUrl(string $audienceId, string $mailchimpId): string
    {
        return sprintf('https://us1.admin.mailchimp.com/lists/members/view?id=%s', $mailchimpId);
    }

    public function getMembersSyncedCount(): int
    {
        return $this->customerRepository->countMailchimpSyncedMembers();
    }

    public function getMembersErrorCount(): int
    {
        return $this->customerRepository->countMailchimpMembersWithError();
    }

    public function getMembersNeverSyncedCount(): int
    {
        return $this->customerRepository->countMailchimpNeverSyncedMembers();
    }

    public function getPendingCartsCount(): int
    {
        return $this->orderRepository->countMailchimpPendingCarts();
    }

    public function getPendingOrdersCount(): int
    {
        return $this->orderRepository->countMailchimpPendingOrders();
    }

    public function parseError(?string $error): ParsedError
    {
        $raw = $error ?? '';

        if (preg_match('/^Mailchimp API error (\d+): (.+)$/s', $raw, $matches) !== 1) {
            return new ParsedError(null, null, null, $raw, $raw);
        }

        $statusCode = (int) $matches[1];
        $body = $matches[2];

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new ParsedError($statusCode, null, null, $raw, $body);
        }

        $title = isset($decoded['title']) && is_string($decoded['title']) ? $decoded['title'] : null;
        $detail = isset($decoded['detail']) && is_string($decoded['detail']) ? $decoded['detail'] : null;
        $prettyRaw = json_encode($decoded, \JSON_PRETTY_PRINT);

        return new ParsedError($statusCode, $title, $detail, $raw, $prettyRaw !== false ? $prettyRaw : $body);
    }
}
