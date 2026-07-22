<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class ParsedError
{
    public function __construct(
        public readonly ?int $statusCode,
        public readonly ?string $title,
        public readonly ?string $detail,
        public readonly string $raw,
        public readonly string $prettyRaw,
    ) {
    }
}
