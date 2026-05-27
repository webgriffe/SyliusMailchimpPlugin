<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Product;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductCreateHandler;

final class ProductCreateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Product/ProductCreateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_product_create_calls_upsert_product(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/product.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $channel = $em->getRepository(Channel::class)->findOneBy(['code' => 'PRODUCT_CREATE_TEST']);
        $product = $em->getRepository(Product::class)->findOneBy(['code' => 'PRODUCT_CREATE_PRODUCT']);

        $handler = self::getContainer()->get(ProductCreateHandler::class);
        $handler(new ProductCreate($product->getId(), $channel->getId(), 'en_US'));

        self::assertCount(1, $this->stub->getUpsertProductCalls());
    }

    public function test_product_create_skips_when_product_not_found(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/product.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $channel = $em->getRepository(Channel::class)->findOneBy(['code' => 'PRODUCT_CREATE_TEST']);

        $handler = self::getContainer()->get(ProductCreateHandler::class);
        $handler(new ProductCreate(99999, $channel->getId(), 'en_US'));

        self::assertCount(0, $this->stub->getUpsertProductCalls());
    }
}
