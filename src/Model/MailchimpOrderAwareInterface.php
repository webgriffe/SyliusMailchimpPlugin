<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

interface MailchimpOrderAwareInterface
{
    public function getMailchimpOrderId(): ?string;

    public function setMailchimpOrderId(?string $mailchimpOrderId): void;

    public function getMailchimpCartId(): ?string;

    public function setMailchimpCartId(?string $mailchimpCartId): void;

    public function getMailchimpOrderError(): ?string;

    public function setMailchimpOrderError(?string $mailchimpOrderError): void;

    public function getMailchimpCartError(): ?string;

    public function setMailchimpCartError(?string $mailchimpCartError): void;
}
