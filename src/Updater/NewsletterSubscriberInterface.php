<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Updater;

interface NewsletterSubscriberInterface
{
    public function subscribe(string $email, string $listId): void;
}
