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
        ];
    }
}
