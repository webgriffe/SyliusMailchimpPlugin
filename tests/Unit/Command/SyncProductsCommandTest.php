<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncProductsCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpProductRepositoryInterface;

final class SyncProductsCommandTest extends TestCase
{
    private MockObject&MailchimpProductRepositoryInterface $productRepository;

    private MockObject&ProductEnqueuerInterface $productEnqueuer;

    private SyncProductsCommand $command;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(MailchimpProductRepositoryInterface::class);
        $this->productEnqueuer = $this->createMock(ProductEnqueuerInterface::class);
        $this->command = new SyncProductsCommand(
            $this->productRepository,
            $this->productEnqueuer,
            false,
        );
    }

    public function test_syncs_all_products_when_no_options(): void
    {
        $product = $this->createProductWithMailchimpChannel();
        $this->productRepository->expects(self::once())->method('findAll')->willReturn([$product]);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, false);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_products_of_channel_when_channel_code_option_provided(): void
    {
        $product = $this->createProductWithMailchimpChannel();
        $this->productRepository->expects(self::once())->method('findMailchimpSyncableByChannel')
            ->with('WEB')
            ->willReturn([$product]);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, false);

        $tester = new CommandTester($this->command);
        $tester->execute(['--channel-code' => 'WEB']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_products_updated_since_when_updated_last_days_option_provided(): void
    {
        $product = $this->createProductWithMailchimpChannel();
        $this->productRepository->expects(self::once())->method('findMailchimpSyncableUpdatedSince')
            ->willReturn([$product]);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, false);

        $tester = new CommandTester($this->command);
        $tester->execute(['--updated-last-days' => '7']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_products_of_channel_updated_since_when_both_options_provided(): void
    {
        $product = $this->createProductWithMailchimpChannel();
        $this->productRepository->expects(self::once())->method('findMailchimpSyncableByChannelUpdatedSince')
            ->with('WEB', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$product]);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, false);

        $tester = new CommandTester($this->command);
        $tester->execute(['--channel-code' => 'WEB', '--updated-last-days' => '7']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_products_without_mailchimp_aware_channel(): void
    {
        $this->productRepository->method('findAll')->willReturn([new Product()]);

        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_product_subjects(): void
    {
        $this->productRepository->method('findAll')->willReturn([new \stdClass()]);

        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    private function createProductWithMailchimpChannel(): Product
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        $product = new Product();
        $product->addChannel($channel);

        return $product;
    }
}
