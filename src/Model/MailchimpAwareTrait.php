<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait MailchimpAwareTrait
{
    #[ORM\Column(name: 'mailchimp_id', type: 'string', length: 32, nullable: true)]
    private ?string $mailchimpId = null;

    #[ORM\Column(name: 'mailchimp_synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $mailchimpSyncedAt = null;

    #[ORM\Column(name: 'mailchimp_error', type: 'text', nullable: true)]
    private ?string $mailchimpError = null;

    public function getMailchimpId(): ?string
    {
        return $this->mailchimpId;
    }

    public function setMailchimpId(?string $mailchimpId): void
    {
        $this->mailchimpId = $mailchimpId;
    }

    public function getMailchimpSyncedAt(): ?\DateTimeImmutable
    {
        return $this->mailchimpSyncedAt;
    }

    public function setMailchimpSyncedAt(?\DateTimeImmutable $mailchimpSyncedAt): void
    {
        $this->mailchimpSyncedAt = $mailchimpSyncedAt;
    }

    public function getMailchimpError(): ?string
    {
        return $this->mailchimpError;
    }

    public function setMailchimpError(?string $mailchimpError): void
    {
        $this->mailchimpError = $mailchimpError;
    }
}
