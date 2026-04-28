<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Util;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class IdSanitizerTest extends TestCase
{
    public function test_keeps_alphanumeric_and_hyphens(): void
    {
        $this->assertSame('PROD-001-RED', IdSanitizer::sanitize('PROD-001-RED'));
        $this->assertSame('order_123', IdSanitizer::sanitize('order_123'));
    }

    public function test_replaces_slashes_with_hyphens(): void
    {
        $this->assertSame('PROD-001-RED', IdSanitizer::sanitize('PROD/001/RED'));
    }

    public function test_replaces_spaces_and_special_chars(): void
    {
        $this->assertSame('Order--1234', IdSanitizer::sanitize('Order #1234'));
    }

    public function test_replaces_dots(): void
    {
        $this->assertSame('product-sku-v1-0', IdSanitizer::sanitize('product.sku.v1.0'));
    }
}
