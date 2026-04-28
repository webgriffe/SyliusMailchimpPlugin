<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

interface ChannelMailchimpAwareInterface
{
    public const NEWSLETTER_POSITIONS = [
        'checkout_addressing',
        'checkout_complete',
        'register',
        'my_account',
    ];

    public function getMailchimpAudienceId(): ?string;

    public function setMailchimpAudienceId(?string $mailchimpAudienceId): void;

    /** @return string[] */
    public function getMailchimpNewsletterPositions(): array;

    /** @param string[] $mailchimpNewsletterPositions */
    public function setMailchimpNewsletterPositions(array $mailchimpNewsletterPositions): void;
}
