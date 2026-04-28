<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareTrait;

final class MailchimpOrderAwareTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class () {
            use MailchimpOrderAwareTrait;
        };
    }

    public function test_mailchimp_order_id_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpOrderId());
    }

    public function test_it_sets_and_gets_mailchimp_order_id(): void
    {
        $this->subject->setMailchimpOrderId('ORDER-001');

        self::assertSame('ORDER-001', $this->subject->getMailchimpOrderId());
    }

    public function test_mailchimp_cart_id_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpCartId());
    }

    public function test_it_sets_and_gets_mailchimp_cart_id(): void
    {
        $this->subject->setMailchimpCartId('CART-001');

        self::assertSame('CART-001', $this->subject->getMailchimpCartId());
    }

    public function test_mailchimp_order_error_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpOrderError());
    }

    public function test_it_sets_and_gets_mailchimp_order_error(): void
    {
        $this->subject->setMailchimpOrderError('Order sync failed');

        self::assertSame('Order sync failed', $this->subject->getMailchimpOrderError());
    }

    public function test_mailchimp_cart_error_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpCartError());
    }

    public function test_it_sets_and_gets_mailchimp_cart_error(): void
    {
        $this->subject->setMailchimpCartError('Cart sync failed');

        self::assertSame('Cart sync failed', $this->subject->getMailchimpCartError());
    }
}
