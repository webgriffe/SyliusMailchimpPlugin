<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Sylius\Component\Core\Model\ProductInterface;

interface ProductEnqueuerInterface
{
    public function enqueue(ProductInterface $product, bool $isNew = false): void;

    public function enqueueRemoval(string $storeId, string $productId): void;

    public function buildProductId(ProductInterface $product): string;
}
