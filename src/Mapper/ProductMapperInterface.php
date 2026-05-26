<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;

interface ProductMapperInterface
{
    public function map(ProductInterface $product, ChannelInterface $channel, string $locale): Product;
}
