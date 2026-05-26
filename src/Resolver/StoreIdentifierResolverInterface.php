<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

interface StoreIdentifierResolverInterface
{
    public function resolve(Audience $audience): string;
}
