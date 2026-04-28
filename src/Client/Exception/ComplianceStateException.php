<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client\Exception;

final class ComplianceStateException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $email,
        private readonly ?string $resubscribeUrl = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forEmail(string $email, ?string $resubscribeUrl = null): self
    {
        return new self(
            sprintf('Member "%s" is in compliance state and cannot be subscribed.', $email),
            $email,
            $resubscribeUrl,
        );
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getResubscribeUrl(): ?string
    {
        return $this->resubscribeUrl;
    }
}
