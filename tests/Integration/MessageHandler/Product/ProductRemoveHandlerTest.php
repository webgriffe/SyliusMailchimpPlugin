<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Product;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductRemoveHandler;

final class ProductRemoveHandlerTest extends KernelTestCase
{
    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_product_remove_calls_remove_product(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(ProductRemoveHandler::class);
        $handler(new ProductRemove('store-id', 'product-id'));

        self::assertCount(1, $this->stub->getRemoveProductCalls());
        self::assertSame('store-id', $this->stub->getRemoveProductCalls()[0]['storeId']);
        self::assertSame('product-id', $this->stub->getRemoveProductCalls()[0]['productId']);
    }
}
