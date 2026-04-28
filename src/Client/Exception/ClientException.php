<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client\Exception;

final class ClientException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromResponse(int $statusCode, string $responseBody): self
    {
        return new self(
            sprintf('Mailchimp API error %d: %s', $statusCode, $responseBody),
            $statusCode,
            $responseBody,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
