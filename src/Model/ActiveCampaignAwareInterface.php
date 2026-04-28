<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

interface ActiveCampaignAwareInterface
{
    public function getMailchimpId(): ?string;

    public function setMailchimpId(?string $mailchimpId): void;

    public function getMailchimpSyncedAt(): ?\DateTimeImmutable;

    public function setMailchimpSyncedAt(?\DateTimeImmutable $mailchimpSyncedAt): void;

    public function getMailchimpError(): ?string;

    public function setMailchimpError(?string $mailchimpError): void;
}
