<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

/** Placeholder — full implementation in Fase 2 (Commit 15). */
final class Product
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $description = '',
    ) {
    }
}
