<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

interface ChannelMailchimpAwareInterface
{
    public function getMailchimpAudienceId(): ?string;

    public function setMailchimpAudienceId(?string $mailchimpAudienceId): void;
}
