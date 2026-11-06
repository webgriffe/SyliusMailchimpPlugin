<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class MergeFields
{
    /**
     * @param string|null $firstName null means "do not send", an empty string clears the value on Mailchimp
     * @param string|null $lastName null means "do not send", an empty string clears the value on Mailchimp
     * @param array<string, mixed> $extra Additional merge fields (e.g. ['PHONE' => '...', 'COMPANY' => '...'])
     */
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly array $extra = [],
    ) {
    }

    /** @param array<string, mixed> $extra */
    public function withExtra(array $extra): self
    {
        return new self($this->firstName, $this->lastName, $extra);
    }
}
