<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;

final class CartMapperTest extends TestCase
{
    use ReflectionIdTrait;

    private MockObject&UrlGeneratorInterface $urlGenerator;

    private CartMapper $mapper;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->mapper = new CartMapper(new EcommerceCustomerMapper(), $this->urlGenerator);
    }

    public function test_maps_order_to_cart(): void
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('john@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');

        $product = new Product();
        $product->setCode('TSHIRT');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $product->addVariant($variant);

        $item = new OrderItem();
        self::setIdOnObject($item, 10);
        $item->setVariant($variant);
        $item->setUnitPrice(1999);
        self::setQuantity($item, 2);

        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $order = new Order();
        self::setIdOnObject($order, 42);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->addItem($item);
        self::setTotal($order, 3998);

        $order->setTokenValue('abc');
        $order->setLocaleCode('it_IT');
        $this->urlGenerator->method('generate')->willReturn('/it_IT/cart/restore/abc');

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('42', $cart['id']);
        $this->assertSame('john@example.com', $cart['customer']['email_address']);
        $this->assertSame('https://example.com/it_IT/cart/restore/abc', $cart['checkout_url']);
        $this->assertSame('EUR', $cart['currency_code']);
        $this->assertSame(39.98, $cart['order_total']);
        $this->assertCount(1, $cart['lines']);
        $this->assertSame('10_TSHIRT-L', $cart['lines'][0]['id']);
        $this->assertSame('TSHIRT', $cart['lines'][0]['product_id']);
        $this->assertSame('TSHIRT-L', $cart['lines'][0]['product_variant_id']);
        $this->assertSame(2, $cart['lines'][0]['quantity']);
        $this->assertSame(19.99, $cart['lines'][0]['price']);
    }

    public function test_skips_item_with_no_variant(): void
    {
        $item = new OrderItem();

        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('x@example.com');

        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $order = new Order();
        self::setIdOnObject($order, 99);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->addItem($item);

        $cart = $this->mapper->map($order, $channel);

        $this->assertCount(0, $cart['lines']);
    }

    public function test_checkout_url_is_the_absolute_restore_url_with_cart_locale(): void
    {
        $channel = new Channel();
        $channel->setHostname('example.com');
        $order = $this->buildOrder('abc', 'it_IT');

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('webgriffe_sylius_mailchimp_cart_restore', ['tokenValue' => 'abc', '_locale' => 'it_IT'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/it_IT/cart/restore/abc')
        ;

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('https://example.com/it_IT/cart/restore/abc', $cart['checkout_url']);
    }

    public function test_checkout_url_keeps_the_scheme_of_the_hostname(): void
    {
        $channel = new Channel();
        $channel->setHostname('http://shop.test/');
        $order = $this->buildOrder('abc', 'it_IT');

        $this->urlGenerator->method('generate')->willReturn('/it_IT/cart/restore/abc');

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('http://shop.test/it_IT/cart/restore/abc', $cart['checkout_url']);
    }

    public function test_checkout_url_omits_the_locale_when_the_cart_has_none(): void
    {
        $channel = new Channel();
        $channel->setHostname('example.com');
        $order = $this->buildOrder('abc', null);

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('webgriffe_sylius_mailchimp_cart_restore', ['tokenValue' => 'abc'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/cart/restore/abc')
        ;

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('https://example.com/cart/restore/abc', $cart['checkout_url']);
    }

    public function test_checkout_url_falls_back_to_the_router_absolute_url_without_hostname(): void
    {
        $channel = new Channel();
        $order = $this->buildOrder('abc', 'it_IT');

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('webgriffe_sylius_mailchimp_cart_restore', ['tokenValue' => 'abc', '_locale' => 'it_IT'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://router.example/it_IT/cart/restore/abc')
        ;

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('https://router.example/it_IT/cart/restore/abc', $cart['checkout_url']);
    }

    private function buildOrder(string $token, ?string $locale): Order
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('x@example.com');

        $order = new Order();
        self::setIdOnObject($order, 5);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->setTokenValue($token);
        if ($locale !== null) {
            $order->setLocaleCode($locale);
        }

        return $order;
    }

    private static function setQuantity(OrderItem $item, int $quantity): void
    {
        $ref = new \ReflectionProperty($item, 'quantity');
        $ref->setValue($item, $quantity);
    }

    private static function setTotal(Order $order, int $total): void
    {
        $ref = new \ReflectionProperty($order, 'total');
        $ref->setValue($order, $total);
    }
}
