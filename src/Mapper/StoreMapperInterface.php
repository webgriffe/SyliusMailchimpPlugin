<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

interface StoreMapperInterface
{
    /** @return array<string, mixed> */
    public function map(Audience $audience): array;
}
