<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\NotFoundException;
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
        private readonly StoreMapperInterface $storeMapper,
        private readonly ProductMapperInterface $productMapper,
        private readonly CartMapperInterface $cartMapper,
        private readonly OrderMapperInterface $orderMapper,
        private readonly EcommerceCustomerMapperInterface $ecommerceCustomerMapper,
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

        // todo: there should be no fallback data as it is coming from maiclhimp, if something is missing we should throw!
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
        $this->logger->debug('[Mailchimp] GET {url}', ['url' => $url]);

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
        $names = array_column($data['tags'] ?? [], 'name');

        $this->logger->debug('[Mailchimp] Fetched {count} tag(s) for member {hash}.', ['count' => count($names), 'hash' => $subscriberHash]);

        return $names;
    }

    #[\Override]
    public function updateMemberTags(string $listId, string $subscriberHash, array $tags): void
    {
        $url = sprintf('%slists/%s/members/%s/tags', $this->baseUrl, $listId, $subscriberHash);
        $payload = [
            'tags' => array_map(static fn (string $tag): array => ['name' => $tag, 'status' => 'active'], $tags),
        ];

        $this->logger->debug('[Mailchimp] POST {url}', ['url' => $url, 'payload' => $payload]);

        $response = $this->httpClient->request('POST', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $subscriberHash);
        }

        $this->logger->info('[Mailchimp] Member tags updated for {hash}.', ['hash' => $subscriberHash]);
    }

    #[\Override]
    public function upsertStore(Audience $audience): void
    {
        $payload = $this->storeMapper->map($audience);
        $storeId = self::stringFromPayload($payload['id'] ?? null);
        $emailAddress = self::stringFromPayload($payload['email_address'] ?? null);

        if (filter_var($emailAddress, \FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot upsert Mailchimp store "%s": invalid email address "%s". ' .
                'Please set a valid contact email on the Sylius channel.',
                $storeId,
                $emailAddress,
            ));
        }

        $getUrl = sprintf('%secommerce/stores/%s', $this->baseUrl, $storeId);
        $this->logger->debug('[Mailchimp] GET {url}', ['url' => $getUrl]);

        $getResponse = $this->httpClient->request('GET', $getUrl, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $getStatus = $getResponse->getStatusCode();
        $isCreation = $getStatus === 404;
        if ($isCreation) {
            $createUrl = sprintf('%secommerce/stores', $this->baseUrl);
            $this->logger->debug('[Mailchimp] POST {url}', ['url' => $createUrl, 'payload' => $payload]);

            $response = $this->httpClient->request('POST', $createUrl, [
                'auth_basic' => ['anystring', $this->getApiKey()],
                'json' => $payload,
            ]);
        } elseif ($getStatus === 200) {
            $patchPayload = $payload;
            unset($patchPayload['id']);
            $this->logger->debug('[Mailchimp] PATCH {url}', ['url' => $getUrl, 'payload' => $patchPayload]);

            $response = $this->httpClient->request('PATCH', $getUrl, [
                'auth_basic' => ['anystring', $this->getApiKey()],
                'json' => $patchPayload,
            ]);
        } else {
            $this->handleErrorResponse($getStatus, $getResponse->getContent(false), $storeId);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $storeId);
        }

        if ($isCreation) {
            $responseBody = $response->getContent(false);
            /** @var array<string, mixed>|null $responseData */
            $responseData = json_decode($responseBody, true);
            if (is_array($responseData) && ($responseData['email_address'] ?? '') === '') {
                throw new ClientException(
                    sprintf(
                        'Mailchimp silently rejected store "%s" creation: the email address "%s" was not accepted. ' .
                        'The email domain may be reserved or blocked by Mailchimp (e.g. example.com).',
                        $storeId,
                        $emailAddress,
                    ),
                    $statusCode,
                    $responseBody,
                );
            }
        }

        $this->logger->info('[Mailchimp] Store {id} upserted.', ['id' => $storeId]);
    }

    #[\Override]
    public function removeStore(string $storeId): void
    {
        $url = sprintf('%secommerce/stores/%s', $this->baseUrl, $storeId);
        $this->logger->debug('[Mailchimp] DELETE {url}', ['url' => $url]);

        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $storeId);
        }

        $this->logger->info('[Mailchimp] Store {id} removed.', ['id' => $storeId]);
    }

    #[\Override]
    public function upsertProduct(string $storeId, ProductInterface $product, ChannelInterface $channel, string $locale): void
    {
        $payload = $this->productMapper->map($product, $channel, $locale);
        $productId = self::stringFromPayload($payload['id'] ?? null);

        $url = sprintf('%secommerce/stores/%s/products/%s', $this->baseUrl, $storeId, $productId);
        $this->logger->debug('[Mailchimp] PUT {url}', ['url' => $url, 'payload' => $payload]);

        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $productId);
        }

        $this->logger->info('[Mailchimp] Product {id} upserted in store {store}.', ['id' => $productId, 'store' => $storeId]);
    }

    #[\Override]
    public function removeProduct(string $storeId, string $productId): void
    {
        $url = sprintf('%secommerce/stores/%s/products/%s', $this->baseUrl, $storeId, $productId);
        $this->logger->debug('[Mailchimp] DELETE {url}', ['url' => $url]);

        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $productId);
        }

        $this->logger->info('[Mailchimp] Product {id} removed from store {store}.', ['id' => $productId, 'store' => $storeId]);
    }

    #[\Override]
    public function upsertCart(string $storeId, OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): void
    {
        $payload = $this->cartMapper->map($order, $channel);
        $cartId = self::stringFromPayload($payload['id'] ?? null);

        $cartUrl = sprintf('%secommerce/stores/%s/carts/%s', $this->baseUrl, $storeId, $cartId);
        $this->logger->debug('[Mailchimp] GET {url}', ['url' => $cartUrl]);

        $getResponse = $this->httpClient->request('GET', $cartUrl, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        if ($getResponse->getStatusCode() === 404) {
            $createUrl = sprintf('%secommerce/stores/%s/carts', $this->baseUrl, $storeId);
            $this->logger->debug('[Mailchimp] POST {url}', ['url' => $createUrl, 'payload' => $payload]);

            $response = $this->httpClient->request('POST', $createUrl, [
                'auth_basic' => ['anystring', $this->getApiKey()],
                'json' => $payload,
            ]);
        } else {
            $patchPayload = $payload;
            unset($patchPayload['id']);
            $this->logger->debug('[Mailchimp] PATCH {url}', ['url' => $cartUrl, 'payload' => $patchPayload]);

            $response = $this->httpClient->request('PATCH', $cartUrl, [
                'auth_basic' => ['anystring', $this->getApiKey()],
                'json' => $patchPayload,
            ]);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $cartId);
        }

        $this->logger->info('[Mailchimp] Cart {id} upserted in store {store}.', ['id' => $cartId, 'store' => $storeId]);
    }

    #[\Override]
    public function removeCart(string $storeId, string $cartId): void
    {
        $url = sprintf('%secommerce/stores/%s/carts/%s', $this->baseUrl, $storeId, $cartId);
        $this->logger->debug('[Mailchimp] DELETE {url}', ['url' => $url]);

        $response = $this->httpClient->request('DELETE', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204 && $statusCode !== 404 && $statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $cartId);
        }

        $this->logger->info('[Mailchimp] Cart {id} removed from store {store}.', ['id' => $cartId, 'store' => $storeId]);
    }

    #[\Override]
    public function upsertOrder(string $storeId, OrderInterface&MailchimpOrderAwareInterface $order, bool $isInRealTime = false): void
    {
        $payload = $this->orderMapper->map($order, $isInRealTime);
        $orderId = self::stringFromPayload($payload['id'] ?? null);

        $this->upsertEcommerceResource(
            sprintf('%secommerce/stores/%s/orders/%s', $this->baseUrl, $storeId, $orderId),
            $payload,
            $orderId,
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
    public function upsertEcommerceCustomer(string $storeId, OrderInterface $order): void
    {
        $payload = $this->ecommerceCustomerMapper->mapFromOrder($order);
        $customerId = self::stringFromPayload($payload['id'] ?? null);

        $this->upsertEcommerceResource(
            sprintf('%secommerce/stores/%s/customers/%s', $this->baseUrl, $storeId, $customerId),
            $payload,
            $customerId,
        );
    }

    #[\Override]
    public function ping(): void
    {
        $url = sprintf('%sping', $this->baseUrl);
        $this->logger->debug('[Mailchimp] GET {url} (ping)', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw ClientException::fromResponse($statusCode, $response->getContent(false));
        }

        $this->logger->info('[Mailchimp] Ping successful.');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    #[\Override]
    public function getAudiences(): array
    {
        $url = sprintf('%slists?count=100', $this->baseUrl);
        $this->logger->debug('[Mailchimp] GET {url}', ['url' => $url]);

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

        $result = array_values(array_map(
            static fn (array $list): array => ['id' => $list['id'], 'name' => $list['name']],
            $data['lists'] ?? [],
        ));

        $this->logger->debug('[Mailchimp] Fetched {count} audience(s).', ['count' => count($result)]);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function upsertEcommerceResource(string $url, array $payload, string $resourceId): void
    {
        $this->logger->debug('[Mailchimp] PUT {url}', ['url' => $url, 'payload' => $payload]);

        $response = $this->httpClient->request('PUT', $url, [
            'auth_basic' => ['anystring', $this->getApiKey()],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->handleErrorResponse($statusCode, $response->getContent(false), $resourceId);
        }

        $this->logger->info('[Mailchimp] Resource {id} upserted.', ['id' => $resourceId]);
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

    private static function stringFromPayload(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
