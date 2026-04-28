<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MailchimpExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('mailchimp_sync_badge', [MailchimpRuntime::class, 'getSyncBadge']),
            new TwigFunction('mailchimp_member_url', [MailchimpRuntime::class, 'getMemberUrl']),
            new TwigFunction('mailchimp_members_synced_count', [MailchimpRuntime::class, 'getMembersSyncedCount']),
            new TwigFunction('mailchimp_members_error_count', [MailchimpRuntime::class, 'getMembersErrorCount']),
            new TwigFunction('mailchimp_members_never_synced_count', [MailchimpRuntime::class, 'getMembersNeverSyncedCount']),
            new TwigFunction('mailchimp_pending_carts_count', [MailchimpRuntime::class, 'getPendingCartsCount']),
            new TwigFunction('mailchimp_pending_orders_count', [MailchimpRuntime::class, 'getPendingOrdersCount']),
        ];
    }
}
