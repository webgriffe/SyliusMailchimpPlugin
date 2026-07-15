<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Client;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\NotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MailchimpClientTest extends TestCase
{
    private const API_KEY = 'testapikey-us1';

    private const LIST_ID = 'abc123';

    private MockObject&HttpClientInterface $httpClient;

    private MockObject&StoreMapperInterface $storeMapper;

    private MockObject&ProductMapperInterface $productMapper;

    private MockObject&CartMapperInterface $cartMapper;

    private MockObject&OrderMapperInterface $orderMapper;

    private MockObject&EcommerceCustomerMapperInterface $ecommerceCustomerMapper;

    private MailchimpClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->storeMapper = $this->createMock(StoreMapperInterface::class);
        $this->productMapper = $this->createMock(ProductMapperInterface::class);
        $this->cartMapper = $this->createMock(CartMapperInterface::class);
        $this->orderMapper = $this->createMock(OrderMapperInterface::class);
        $this->ecommerceCustomerMapper = $this->createMock(EcommerceCustomerMapperInterface::class);
        $this->client = new MailchimpClient(
            $this->httpClient,
            new NullLogger(),
            self::API_KEY,
            $this->storeMapper,
            $this->productMapper,
            $this->cartMapper,
            $this->orderMapper,
            $this->ecommerceCustomerMapper,
        );
    }

    public function test_upsert_member_returns_subscriber_id(): void
    {
        $response = $this->mockResponse(200, '{"id":"abc123hash"}');
        $this->httpClient->expects($this->once())->method('request')
            ->with('PUT', $this->stringContains('lists/' . self::LIST_ID . '/members/'))
            ->willReturn($response);

        $member = new Member('test@example.com', 'subscribed', new MergeFields('John', 'Doe'));
        $id = $this->client->upsertMember(self::LIST_ID, $member);

        $this->assertSame('abc123hash', $id);
    }

    public function test_upsert_member_fallback_to_subscriber_hash_if_no_id_in_response(): void
    {
        $email = 'test@example.com';
        $expectedHash = md5(strtolower($email));
        $response = $this->mockResponse(200, '{}');
        $this->httpClient->method('request')->willReturn($response);

        $member = new Member($email, 'subscribed', new MergeFields('John', 'Doe'));
        $id = $this->client->upsertMember(self::LIST_ID, $member);

        $this->assertSame($expectedHash, $id);
    }

    public function test_upsert_member_throws_client_exception_on_4xx(): void
    {
        $this->expectException(ClientException::class);

        $response = $this->mockResponse(400, '{"title":"Bad Request","detail":"invalid"}');
        $this->httpClient->method('request')->willReturn($response);

        $member = new Member('bad@example.com', 'subscribed', new MergeFields('', ''));
        $this->client->upsertMember(self::LIST_ID, $member);
    }

    public function test_upsert_member_throws_compliance_state_exception(): void
    {
        $this->expectException(ComplianceStateException::class);

        $body = '{"title":"Member In Compliance State","detail":"...","extra":{"resubscribe_url":"https://example.com/resub"}}';
        $response = $this->mockResponse(400, $body);
        $this->httpClient->method('request')->willReturn($response);

        $member = new Member('compliance@example.com', 'subscribed', new MergeFields('', ''));
        $this->client->upsertMember(self::LIST_ID, $member);
    }

    public function test_upsert_member_compliance_exception_carries_email_and_resubscribe_url(): void
    {
        $body = '{"title":"Member In Compliance State","detail":"...","extra":{"resubscribe_url":"https://resub.example.com"}}';
        $response = $this->mockResponse(400, $body);
        $this->httpClient->method('request')->willReturn($response);

        $member = new Member('compliance@example.com', 'subscribed', new MergeFields('', ''));

        try {
            $this->client->upsertMember(self::LIST_ID, $member);
            $this->fail('Expected ComplianceStateException');
        } catch (ComplianceStateException $e) {
            $this->assertSame('compliance@example.com', $e->getEmail());
            $this->assertSame('https://resub.example.com', $e->getResubscribeUrl());
        }
    }

    public function test_get_member_returns_member_on_200(): void
    {
        $body = '{"email_address":"test@example.com","status":"subscribed","merge_fields":{"FNAME":"John","LNAME":"Doe"}}';
        $response = $this->mockResponse(200, $body);
        $this->httpClient->method('request')->willReturn($response);

        $member = $this->client->getMember(self::LIST_ID, md5('test@example.com'));

        $this->assertNotNull($member);
        $this->assertSame('test@example.com', $member->emailAddress);
        $this->assertSame('subscribed', $member->status);
        $this->assertSame('John', $member->mergeFields->firstName);
    }

    public function test_get_member_returns_null_on_404(): void
    {
        $response = $this->mockResponse(404, '{"title":"Resource Not Found"}');
        $this->httpClient->method('request')->willReturn($response);

        $member = $this->client->getMember(self::LIST_ID, 'nonexistent');

        $this->assertNull($member);
    }

    public function test_remove_member_calls_delete(): void
    {
        $response = $this->mockResponse(204, '');
        $this->httpClient->expects($this->once())->method('request')
            ->with('DELETE', $this->stringContains('members/'))
            ->willReturn($response);

        $this->client->removeMember(self::LIST_ID, 'somehash');
    }

    public function test_remove_member_silently_handles_404(): void
    {
        $response = $this->mockResponse(404, '{"title":"Resource Not Found"}');
        $this->httpClient->method('request')->willReturn($response);

        // Should not throw
        $this->client->removeMember(self::LIST_ID, 'nonexistent');
        $this->addToAssertionCount(1);
    }

    public function test_get_member_tags_returns_array_of_names(): void
    {
        $body = '{"tags":[{"name":"VIP"},{"name":"Newsletter"}]}';
        $response = $this->mockResponse(200, $body);
        $this->httpClient->method('request')->willReturn($response);

        $tags = $this->client->getMemberTags(self::LIST_ID, 'hash');

        $this->assertSame(['VIP', 'Newsletter'], $tags);
    }

    public function test_update_member_tags_sends_active_status(): void
    {
        $response = $this->mockResponse(204, '');
        $this->httpClient->expects($this->once())->method('request')
            ->with('POST', $this->stringContains('/tags'), $this->callback(
                static fn (array $opts): bool => ($opts['json']['tags'][0]['status'] ?? '') === 'active',
            ))
            ->willReturn($response);

        $this->client->updateMemberTags(self::LIST_ID, 'hash', ['VIP']);
    }

    public function test_ping_throws_client_exception_on_error(): void
    {
        $this->expectException(ClientException::class);

        $response = $this->mockResponse(401, '{"title":"API Key Invalid"}');
        $this->httpClient->method('request')->willReturn($response);

        $this->client->ping();
    }

    public function test_ping_succeeds_on_200(): void
    {
        $response = $this->mockResponse(200, '{"health_status":"Everything\'s Chimpy!"}');
        $this->httpClient->method('request')->willReturn($response);

        $this->client->ping();
        $this->addToAssertionCount(1);
    }

    public function test_get_audiences_returns_list(): void
    {
        $body = '{"lists":[{"id":"abc","name":"My Audience"},{"id":"def","name":"Second Audience"}]}';
        $response = $this->mockResponse(200, $body);
        $this->httpClient->method('request')->willReturn($response);

        $audiences = $this->client->getAudiences();

        $this->assertCount(2, $audiences);
        $this->assertSame('abc', $audiences[0]['id']);
        $this->assertSame('My Audience', $audiences[0]['name']);
    }

    public function test_remove_member_throws_on_unexpected_error(): void
    {
        $this->expectException(ClientException::class);

        $response = $this->mockResponse(500, '{"title":"Server Error"}');
        $this->httpClient->method('request')->willReturn($response);

        $this->client->removeMember(self::LIST_ID, 'hash');
    }

    public function test_upsert_member_throws_not_found_on_404_error(): void
    {
        $this->expectException(NotFoundException::class);

        $response = $this->mockResponse(404, '{"title":"Resource Not Found"}');
        // GET endpoint returns 404 → null, but PUT returning 404 should throw NotFoundException
        $this->httpClient->method('request')
            ->with('PUT', $this->anything(), $this->anything())
            ->willReturn($response);

        $member = new Member('test@example.com', 'subscribed', new MergeFields('', ''));
        $this->client->upsertMember(self::LIST_ID, $member);
    }

    public function test_upsert_store_posts_on_404(): void
    {
        $notFound = $this->mockResponse(404, '{"status":404}');
        $created = $this->mockResponse(200, '{"id":"store-1","email_address":"admin@myshop.com"}');

        $this->httpClient->expects($this->exactly(2))->method('request')
            ->willReturnCallback(function (string $method) use ($notFound, $created): ResponseInterface {
                if ($method === 'GET') {
                    return $notFound;
                }

                return $created;
            });

        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMock(ChannelInterface::class);
        $audience = new Audience('list-1', $channel);
        $this->storeMapper->method('map')->willReturn([
            'id' => 'store-1',
            'name' => 'My Shop',
            'domain' => 'myshop.com',
            'email_address' => 'admin@myshop.com',
            'currency_code' => 'EUR',
            'primary_locale' => 'it',
            'list_id' => 'list-1',
            'platform' => 'Sylius',
        ]);
        $this->client->upsertStore($audience);
    }

    public function test_upsert_store_patches_on_existing(): void
    {
        $existing = $this->mockResponse(200, '{"id":"store-1"}');
        $updated = $this->mockResponse(200, '{}');

        $this->httpClient->expects($this->exactly(2))->method('request')
            ->willReturnCallback(function (string $method) use ($existing, $updated): ResponseInterface {
                if ($method === 'GET') {
                    return $existing;
                }

                return $updated;
            });

        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMock(ChannelInterface::class);
        $audience = new Audience('list-1', $channel);
        $this->storeMapper->method('map')->willReturn([
            'id' => 'store-1',
            'name' => 'My Shop',
            'domain' => 'myshop.com',
            'email_address' => 'admin@myshop.com',
            'currency_code' => 'EUR',
            'primary_locale' => 'it',
            'list_id' => 'list-1',
            'platform' => 'Sylius',
        ]);
        $this->client->upsertStore($audience);
    }

    public function test_upsert_product_includes_variants(): void
    {
        $response = $this->mockResponse(200, '{}');
        $this->httpClient->expects($this->once())->method('request')
            ->with('PUT', $this->stringContains('ecommerce/stores/store-1/products/prod-1'), $this->callback(
                static fn (array $options): bool => count($options['json']['variants']) === 1 &&
                    $options['json']['variants'][0]['id'] === 'var-1',
            ))
            ->willReturn($response);

        $product = $this->createMock(ProductInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $this->productMapper->method('map')->willReturn([
            'id' => 'prod-1',
            'title' => 'T-Shirt',
            'url' => 'https://example.com',
            'variants' => [['id' => 'var-1', 'title' => 'Red', 'url' => 'https://example.com', 'sku' => 'SKU', 'price' => 9.99, 'inventory_quantity' => 0]],
        ]);
        $this->client->upsertProduct('store-1', $product, $channel, 'en_US');
    }

    public function test_upsert_cart_posts_on_404(): void
    {
        $notFound = $this->mockResponse(404, '{"status":404}');
        $created = $this->mockResponse(200, '{}');

        $capturedPost = null;
        $this->httpClient->expects($this->exactly(2))->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options) use ($notFound, $created, &$capturedPost): ResponseInterface {
                if ($method === 'GET') {
                    return $notFound;
                }

                $capturedPost = $options['json'] ?? [];

                return $created;
            });

        $order = $this->createMock(OrderInterface::class);
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $this->cartMapper->method('map')->willReturn([
            'id' => 'cart-1',
            'customer' => ['id' => 'cust-1', 'email_address' => 'user@example.com', 'opt_in_status' => false],
            'checkout_url' => 'https://example.com/checkout',
            'currency_code' => 'EUR',
            'order_total' => 9.99,
            'lines' => [['id' => 'line-1', 'product_id' => 'prod-1', 'product_variant_id' => 'var-1', 'quantity' => 1, 'price' => 9.99]],
        ]);
        $this->client->upsertCart('store-1', $order, $channel);

        $this->assertSame('user@example.com', $capturedPost['customer']['email_address'] ?? null);
        $this->assertCount(1, $capturedPost['lines'] ?? []);
    }

    public function test_upsert_cart_patches_on_existing(): void
    {
        $existing = $this->mockResponse(200, '{"id":"cart-1"}');
        $updated = $this->mockResponse(200, '{}');

        $this->httpClient->expects($this->exactly(2))->method('request')
            ->willReturnCallback(static function (string $method) use ($existing, $updated): ResponseInterface {
                if ($method === 'GET') {
                    return $existing;
                }

                return $updated;
            });

        $order = $this->createMock(OrderInterface::class);
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $this->cartMapper->method('map')->willReturn([
            'id' => 'cart-1',
            'customer' => ['id' => 'cust-1', 'email_address' => 'user@example.com', 'opt_in_status' => false],
            'checkout_url' => 'https://example.com/checkout',
            'currency_code' => 'EUR',
            'order_total' => 9.99,
            'lines' => [],
        ]);
        $this->client->upsertCart('store-1', $order, $channel);
    }

    public function test_upsert_order_includes_processed_at_when_set(): void
    {
        $response = $this->mockResponse(200, '{}');
        $this->httpClient->expects($this->once())->method('request')
            ->with('PUT', $this->stringContains('ecommerce/stores/store-1/orders/order-1'), $this->callback(
                static fn (array $options): bool => isset($options['json']['processed_at_foreign']),
            ))
            ->willReturn($response);

        /** @var OrderInterface&MailchimpOrderAwareInterface $order */
        $order = $this->createMockForIntersectionOfInterfaces([OrderInterface::class, MailchimpOrderAwareInterface::class]);
        $this->orderMapper->method('map')->willReturn([
            'id' => 'order-1',
            'customer' => ['id' => 'cust-1', 'email_address' => 'user@example.com', 'opt_in_status' => false],
            'currency_code' => 'EUR',
            'order_total' => 99.0,
            'tax_total' => 0.0,
            'shipping_total' => 0.0,
            'discount_total' => 0.0,
            'lines' => [],
            'processed_at_foreign' => (new \DateTimeImmutable('2025-01-01'))->format(\DateTimeInterface::ATOM),
        ]);
        $this->client->upsertOrder('store-1', $order);
    }

    private function mockResponse(int $statusCode, string $body): MockObject&ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($body);

        return $response;
    }
}
