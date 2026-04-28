<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class Member
{
    /**
     * @param string[] $tags
     * @param array<string, bool> $interests
     */
    public function __construct(
        public readonly string $emailAddress,
        public readonly string $status,
        public readonly MergeFields $mergeFields,
        public readonly array $tags = [],
        public readonly array $interests = [],
        public readonly string $language = '',
        public readonly ?string $ipSignup = null,
    ) {
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->emailAddress,
            $status,
            $this->mergeFields,
            $this->tags,
            $this->interests,
            $this->language,
            $this->ipSignup,
        );
    }

    /** @param string[] $tags */
    public function withTags(array $tags): self
    {
        return new self(
            $this->emailAddress,
            $this->status,
            $this->mergeFields,
            $tags,
            $this->interests,
            $this->language,
            $this->ipSignup,
        );
    }

    /** @param array<string, bool> $interests */
    public function withInterests(array $interests): self
    {
        return new self(
            $this->emailAddress,
            $this->status,
            $this->mergeFields,
            $this->tags,
            $interests,
            $this->language,
            $this->ipSignup,
        );
    }
}
