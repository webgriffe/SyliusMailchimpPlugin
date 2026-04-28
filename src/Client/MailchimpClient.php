<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\NotFoundException;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\CartLine;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\OrderLine;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class MailchimpClient implements MailchimpClientInterface
{
    private const BASE_URL = 'https://<dc>.api.mailchimp.com/3.0/';

    private const COMPLIANCE_TITLES = ['Member In Compliance State', 'Forgotten Email Not Subscribed'];

    private string $baseUrl;

    private readonly string $apiKey;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $apiKey,
    ) {
        $this->apiKey = $apiKey;
        $dc = substr($apiKey, (int) strrpos($apiKey, '-') + 1);
        $this->baseUrl = str_replace('<dc>', $dc, self::BASE_URL);
    }

    #[\Override]
    public function upsertMember(string $listId, Member $member): string
    {
        $subscriberHash = md5(strtolower($member->emailAddress));
        $url = sprintf('%slists/%s/members/%s', $this->baseUrl, $listId, $subscriberHash);

        $payload = $this->serializeMember($member);
        $this->logger->debug('[Mailchimp] PUT {url}', ['url' => $url, 'payload' => $payload]);

        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);

        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $body, $member->emailAddress);
        }

        /** @var array{id?: string} $data */
        $data = json_decode($body, true);
        $id = $data['id'] ?? $subscriberHash;

        $this->logger->info('[Mailchimp] Member upserted: {email}', ['email' => $member->emailAddress]);

        return $id;
    }

    #[\Override]
    public function getMember(string $listId, string $subscriberHash): ?Member
    {
        $url = sprintf('%slists/%s/members/%s', $this->baseUrl, $listId, $subscriberHash);
        $this->logger->debug('[Mailchimp] GET {url}', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 404) {
            return null;
        }

        $body = $response->getContent(false);
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $body, $subscriberHash);
        }

        /** @var array{email_address?: string, status?: string, merge_fields?: array{FNAME?: string, LNAME?: string}} $data */
        $data = json_decode($body, true);

        return new Member(
            $data['email_address'] ?? '',
            $data['status'] ?? 'subscribed',
            new MergeFields(
                $data['merge_fields']['FNAME'] ?? '',
                $data['merge_fields']['LNAME'] ?? '',
            ),
        );
    }

    #[\Override]
    public function removeMember(string $listId, string $subscriberHash): void
    {
        $url = sprintf('%slists/%s/members/%s', $this->baseUrl, $listId, $subscriberHash);
        $this->logger->debug('[Mailchimp] DELETE {url}', ['url' => $url]);

        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 404) {
            $this->logger->warning('[Mailchimp] Member not found for deletion: {hash}', ['hash' => $subscriberHash]);

            return;
        }

        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $subscriberHash);
        }

        $this->logger->info('[Mailchimp] Member removed: {hash}', ['hash' => $subscriberHash]);
    }

    #[\Override]
    public function getMemberTags(string $listId, string $subscriberHash): array
    {
        $url = sprintf('%slists/%s/members/%s/tags', $this->baseUrl, $listId, $subscriberHash);
        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $body, $subscriberHash);
        }

        /** @var array{tags?: array<array{name: string}>} $data */
        $data = json_decode($body, true);

        return array_column($data['tags'] ?? [], 'name');
    }

    #[\Override]
    public function updateMemberTags(string $listId, string $subscriberHash, array $tags): void
    {
        $url = sprintf('%slists/%s/members/%s/tags', $this->baseUrl, $listId, $subscriberHash);
        $payload = [
            'tags' => array_map(static fn (string $tag): array => ['name' => $tag, 'status' => 'active'], $tags),
        ];

        $response = $this->httpClient->request('POST', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $subscriberHash);
        }
    }

    #[\Override]
    public function upsertStore(string $storeId, Store $store): void
    {
        $url = sprintf('%secommerce/stores/%s', $this->baseUrl, $storeId);
        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => [
                'id' => $store->id,
                'name' => $store->name,
                'domain' => $store->domain,
                'email_address' => $store->emailAddress,
                'currency_code' => $store->currencyCode,
                'primary_locale' => $store->primaryLocale,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $storeId);
        }
    }

    #[\Override]
    public function removeStore(string $storeId): void
    {
        $url = sprintf('%secommerce/stores/%s', $this->baseUrl, $storeId);
        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $storeId);
        }
    }

    #[\Override]
    public function upsertProduct(string $storeId, Product $product): void
    {
        $url = sprintf('%secommerce/stores/%s/products/%s', $this->baseUrl, $storeId, $product->id);
        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => [
                'id' => $product->id,
                'title' => $product->title,
                'url' => $product->url,
                'description' => $product->description,
                'variants' => [],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $product->id);
        }
    }

    #[\Override]
    public function removeProduct(string $storeId, string $productId): void
    {
        $url = sprintf('%secommerce/stores/%s/products/%s', $this->baseUrl, $storeId, $productId);
        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $productId);
        }
    }

    #[\Override]
    public function upsertCart(string $storeId, Cart $cart): void
    {
        $this->upsertEcommerceResource(
            sprintf('%secommerce/stores/%s/carts/%s', $this->baseUrl, $storeId, $cart->id),
            [
                'id' => $cart->id,
                'customer' => $this->serializeEcommerceCustomer($cart->customer),
                'checkout_url' => $cart->checkoutUrl,
                'currency_code' => $cart->currencyCode,
                'order_total' => $cart->orderTotal,
                'lines' => array_map([$this, 'serializeCartLine'], $cart->lines),
            ],
            $cart->id,
        );
    }

    #[\Override]
    public function removeCart(string $storeId, string $cartId): void
    {
        $url = sprintf('%secommerce/stores/%s/carts/%s', $this->baseUrl, $storeId, $cartId);
        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $cartId);
        }
    }

    #[\Override]
    public function upsertOrder(string $storeId, Order $order): void
    {
        $payload = [
            'id' => $order->id,
            'customer' => $this->serializeEcommerceCustomer($order->customer),
            'currency_code' => $order->currencyCode,
            'order_total' => $order->orderTotal,
            'tax_total' => $order->taxTotal,
            'shipping_total' => $order->shippingTotal,
            'discount_total' => $order->discountTotal,
            'lines' => array_map([$this, 'serializeOrderLine'], $order->lines),
        ];

        if ($order->processedAt !== null) {
            $payload['processed_at_foreign'] = $order->processedAt->format(\DateTimeInterface::ATOM);
        }

        $this->upsertEcommerceResource(
            sprintf('%secommerce/stores/%s/orders/%s', $this->baseUrl, $storeId, $order->id),
            $payload,
            $order->id,
        );
    }

    #[\Override]
    public function removeOrder(string $storeId, string $orderId): void
    {
        $url = sprintf('%secommerce/stores/%s/orders/%s', $this->baseUrl, $storeId, $orderId);
        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $orderId);
        }
    }

    #[\Override]
    public function upsertEcommerceCustomer(string $storeId, EcommerceCustomer $customer): void
    {
        $this->upsertEcommerceResource(
            sprintf('%secommerce/stores/%s/customers/%s', $this->baseUrl, $storeId, $customer->id),
            $this->serializeEcommerceCustomer($customer),
            $customer->id,
        );
    }

    #[\Override]
    public function ping(): void
    {
        $url = sprintf('%sping', $this->baseUrl);
        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw ClientException::fromResponse($statusCode, $response->getContent(false));
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    #[\Override]
    public function getAudiences(): array
    {
        $url = sprintf('%slists?count=100', $this->baseUrl);
        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $body, 'lists');
        }

        /** @var array{lists?: array<array{id: string, name: string}>} $data */
        $data = json_decode($body, true);

        return array_values(array_map(
            static fn (array $list): array => ['id' => $list['id'], 'name' => $list['name']],
            $data['lists'] ?? [],
        ));
    }

    /** @param array<string, mixed> $payload */
    private function upsertEcommerceResource(string $url, array $payload, string $resourceId): void
    {
        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $resourceId);
        }
    }

    /** @return array<string, mixed> */
    private function serializeMember(Member $member): array
    {
        $payload = [
            'email_address' => $member->emailAddress,
            'status' => $member->status,
            'merge_fields' => array_merge(
                [
                    'FNAME' => $member->mergeFields->firstName,
                    'LNAME' => $member->mergeFields->lastName,
                ],
                $member->mergeFields->extra,
            ),
        ];

        if ($member->tags !== []) {
            $payload['tags'] = $member->tags;
        }

        if ($member->interests !== []) {
            $payload['interests'] = $member->interests;
        }

        if ($member->language !== '') {
            $payload['language'] = $member->language;
        }

        if ($member->ipSignup !== null) {
            $payload['ip_signup'] = $member->ipSignup;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function serializeEcommerceCustomer(EcommerceCustomer $customer): array
    {
        $payload = [
            'id' => $customer->id,
            'email_address' => $customer->emailAddress,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'opt_in_status' => $customer->optInStatus,
        ];

        if ($customer->address !== null) {
            $payload['address'] = [
                'name' => $customer->address->name,
                'address1' => $customer->address->address1,
                'address2' => $customer->address->address2,
                'city' => $customer->address->city,
                'province' => $customer->address->province,
                'province_code' => $customer->address->provinceCode,
                'postal_code' => $customer->address->postalCode,
                'country' => $customer->address->country,
                'country_code' => $customer->address->countryCode,
            ];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function serializeCartLine(CartLine $line): array
    {
        return [
            'id' => $line->id,
            'product_id' => $line->productId,
            'product_variant_id' => $line->productVariantId,
            'quantity' => $line->quantity,
            'price' => $line->price,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrderLine(OrderLine $line): array
    {
        return [
            'id' => $line->id,
            'product_id' => $line->productId,
            'product_variant_id' => $line->productVariantId,
            'quantity' => $line->quantity,
            'price' => $line->price,
            'discount' => $line->discount,
        ];
    }

    private function handleErrorResponse(int $statusCode, string $body, string $context): never
    {
        /** @var array{title?: string, detail?: string, extra?: array{resubscribe_url?: string}} $data */
        $data = json_decode($body, true) ?? [];
        $title = $data['title'] ?? '';

        if ($statusCode === 404) {
            throw NotFoundException::forResource($context, $context);
        }

        if (in_array($title, self::COMPLIANCE_TITLES, true)) {
            $resubscribeUrl = $data['extra']['resubscribe_url'] ?? null;

            throw ComplianceStateException::forEmail($context, $resubscribeUrl);
        }

        $this->logger->error('[Mailchimp] API error {status}: {body}', ['status' => $statusCode, 'body' => $body]);

        throw ClientException::fromResponse($statusCode, $body);
    }

    private function getApiKey(): string
    {
        return $this->apiKey;
    }
}
