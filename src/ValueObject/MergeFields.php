<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class MergeFields
{
    /**
     * @param array<string, mixed> $extra Additional merge fields (e.g. ['PHONE' => '...', 'COMPANY' => '...'])
     */
    public function __construct(
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly array $extra = [],
    ) {
    }

    /** @param array<string, mixed> $extra */
    public function withExtra(array $extra): self
    {
        return new self($this->firstName, $this->lastName, $extra);
    }
}
