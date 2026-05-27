<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class StoreValueObjectTest extends TestCase
{
    public function test_store_stores_all_fields(): void
    {
        $store = new Store('store-1', 'My Shop', 'myshop.com', 'admin@myshop.com', 'EUR', 'it_IT');

        $this->assertSame('store-1', $store->id);
        $this->assertSame('My Shop', $store->name);
        $this->assertSame('EUR', $store->currencyCode);
        $this->assertSame('it_IT', $store->primaryLocale);
    }
}
