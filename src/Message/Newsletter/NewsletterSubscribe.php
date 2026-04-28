<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Newsletter;

final class NewsletterSubscribe
{
    public function __construct(
        public readonly string $email,
        public readonly string $listId,
    ) {
    }
}
