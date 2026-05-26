<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

interface StoreMapperInterface
{
    public function map(Audience $audience): Store;
}
