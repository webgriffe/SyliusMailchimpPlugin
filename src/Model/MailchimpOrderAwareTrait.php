<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait MailchimpOrderAwareTrait
{
    #[ORM\Column(name: 'mailchimp_order_id', type: 'string', length: 255, nullable: true)]
    private ?string $mailchimpOrderId = null;

    #[ORM\Column(name: 'mailchimp_cart_id', type: 'string', length: 255, nullable: true)]
    private ?string $mailchimpCartId = null;

    #[ORM\Column(name: 'mailchimp_order_error', type: 'text', nullable: true)]
    private ?string $mailchimpOrderError = null;

    #[ORM\Column(name: 'mailchimp_cart_error', type: 'text', nullable: true)]
    private ?string $mailchimpCartError = null;

    public function getMailchimpOrderId(): ?string
    {
        return $this->mailchimpOrderId;
    }

    public function setMailchimpOrderId(?string $mailchimpOrderId): void
    {
        $this->mailchimpOrderId = $mailchimpOrderId;
    }

    public function getMailchimpCartId(): ?string
    {
        return $this->mailchimpCartId;
    }

    public function setMailchimpCartId(?string $mailchimpCartId): void
    {
        $this->mailchimpCartId = $mailchimpCartId;
    }

    public function getMailchimpOrderError(): ?string
    {
        return $this->mailchimpOrderError;
    }

    public function setMailchimpOrderError(?string $mailchimpOrderError): void
    {
        $this->mailchimpOrderError = $mailchimpOrderError;
    }

    public function getMailchimpCartError(): ?string
    {
        return $this->mailchimpCartError;
    }

    public function setMailchimpCartError(?string $mailchimpCartError): void
    {
        $this->mailchimpCartError = $mailchimpCartError;
    }
}
