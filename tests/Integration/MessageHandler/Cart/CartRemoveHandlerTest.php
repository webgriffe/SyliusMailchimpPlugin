<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Cart;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartRemoveHandler;

final class CartRemoveHandlerTest extends KernelTestCase
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

    public function test_cart_remove_calls_remove_cart(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(CartRemoveHandler::class);
        $handler(new CartRemove('store-id', 'cart-id'));

        self::assertCount(1, $this->stub->getRemoveCartCalls());
        self::assertSame('store-id', $this->stub->getRemoveCartCalls()[0]['storeId']);
        self::assertSame('cart-id', $this->stub->getRemoveCartCalls()[0]['cartId']);
    }
}
