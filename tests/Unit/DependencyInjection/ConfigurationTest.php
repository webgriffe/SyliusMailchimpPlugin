<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Webgriffe\SyliusMailchimpPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;

    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function test_it_requires_api_key(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/api_key/');

        $this->processor->processConfiguration($this->configuration, [[]]);
    }

    public function test_it_rejects_empty_api_key(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [['api_key' => '']]);
    }

    public function test_it_accepts_valid_minimal_config(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['api_key' => 'abc123-us1'],
        ]);

        self::assertSame('abc123-us1', $config['api_key']);
        self::assertSame('subscribed', $config['member_default_status']);
        self::assertSame('', $config['webhook_secret']);
        self::assertTrue($config['send_unpaid_orders_as_carts']);
        self::assertSame('main', $config['mapper']['product']['image_type']);
        self::assertSame('sylius_medium', $config['mapper']['product']['image_filter']);
    }

    public function test_it_accepts_pending_as_member_default_status(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['api_key' => 'abc123-us1', 'member_default_status' => 'pending'],
        ]);

        self::assertSame('pending', $config['member_default_status']);
    }

    public function test_it_rejects_invalid_member_default_status(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            ['api_key' => 'abc123-us1', 'member_default_status' => 'invalid'],
        ]);
    }

    public function test_it_accepts_custom_mapper_product_config(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            [
                'api_key' => 'abc123-us1',
                'mapper' => [
                    'product' => [
                        'image_type' => 'thumbnail',
                        'image_filter' => 'sylius_large',
                    ],
                ],
            ],
        ]);

        self::assertSame('thumbnail', $config['mapper']['product']['image_type']);
        self::assertSame('sylius_large', $config['mapper']['product']['image_filter']);
    }

    public function test_it_accepts_send_unpaid_orders_as_carts_false(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['api_key' => 'abc123-us1', 'send_unpaid_orders_as_carts' => false],
        ]);

        self::assertFalse($config['send_unpaid_orders_as_carts']);
    }

    public function test_it_accepts_webhook_secret(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['api_key' => 'abc123-us1', 'webhook_secret' => 'my-secret'],
        ]);

        self::assertSame('my-secret', $config['webhook_secret']);
    }
}
