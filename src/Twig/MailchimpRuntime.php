<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;

final class MailchimpRuntime implements RuntimeExtensionInterface
{
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
}
