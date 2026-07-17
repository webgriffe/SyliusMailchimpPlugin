<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Product;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductUpdateHandler;

final class ProductUpdateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Product/ProductUpdateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_product_update_calls_upsert_product(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/product.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $channel = $em->getRepository(Channel::class)->findOneBy(['code' => 'PRODUCT_UPDATE_TEST']);
        $product = $em->getRepository(Product::class)->findOneBy(['code' => 'PRODUCT_UPDATE_PRODUCT']);

        $handler = self::getContainer()->get(ProductUpdateHandler::class);
        $handler(new ProductUpdate($product->getId(), $channel->getId(), 'en_US'));

        self::assertCount(1, $this->stub->getUpsertProductCalls());
    }

    public function test_product_update_throws_on_transient_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/product.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $channel = $em->getRepository(Channel::class)->findOneBy(['code' => 'PRODUCT_UPDATE_TEST']);
        $product = $em->getRepository(Product::class)->findOneBy(['code' => 'PRODUCT_UPDATE_PRODUCT']);
        $this->stub->failWith('upsertProduct');

        $handler = self::getContainer()->get(ProductUpdateHandler::class);

        $this->expectException(ClientException::class);
        $handler(new ProductUpdate($product->getId(), $channel->getId(), 'en_US'));
    }

    public function test_product_update_does_not_throw_on_permanent_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/product.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $channel = $em->getRepository(Channel::class)->findOneBy(['code' => 'PRODUCT_UPDATE_TEST']);
        $product = $em->getRepository(Product::class)->findOneBy(['code' => 'PRODUCT_UPDATE_PRODUCT']);
        $this->stub->failWith('upsertProduct', statusCode: 400);

        $handler = self::getContainer()->get(ProductUpdateHandler::class);
        $handler(new ProductUpdate($product->getId(), $channel->getId(), 'en_US'));

        self::assertCount(0, $this->stub->getUpsertProductCalls());
    }
}
