<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client;

use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class StubMailchimpClient implements MailchimpClientInterface
{
    /**
     * Static so that calls recorded during the HTTP request kernel are visible
     * to the Behat context kernel (FriendsOfBehat\SymfonyExtension uses two separate kernels).
     *
     * @var array<array{listId: string, member: Member}>
     */
    private static array $upsertMemberCalls = [];

    /** @var array<array{storeId: string, cart: Cart}> */
    private static array $upsertCartCalls = [];

    /** @var array<array{storeId: string, cartId: string}> */
    private static array $removeCartCalls = [];

    /** @var array<array{storeId: string, order: Order}> */
    private static array $upsertOrderCalls = [];

    /** @var array<array{storeId: string, orderId: string}> */
    private static array $removeOrderCalls = [];

    public function reset(): void
    {
        self::$upsertMemberCalls = [];
        self::$upsertCartCalls = [];
        self::$removeCartCalls = [];
        self::$upsertOrderCalls = [];
        self::$removeOrderCalls = [];
    }

    /** @return array<array{listId: string, member: Member}> */
    public function getUpsertMemberCalls(): array
    {
        return self::$upsertMemberCalls;
    }

    /** @return array<array{storeId: string, cart: Cart}> */
    public function getUpsertCartCalls(): array
    {
        return self::$upsertCartCalls;
    }

    /** @return array<array{storeId: string, cartId: string}> */
    public function getRemoveCartCalls(): array
    {
        return self::$removeCartCalls;
    }

    /** @return array<array{storeId: string, order: Order}> */
    public function getUpsertOrderCalls(): array
    {
        return self::$upsertOrderCalls;
    }

    /** @return array<array{storeId: string, orderId: string}> */
    public function getRemoveOrderCalls(): array
    {
        return self::$removeOrderCalls;
    }

    public function getLastUpsertOrderCall(): ?Order
    {
        $calls = self::$upsertOrderCalls;
        if ($calls === []) {
            return null;
        }

        return $calls[array_key_last($calls)]['order'];
    }

    #[\Override]
    public function upsertMember(string $listId, Member $member): string
    {
        self::$upsertMemberCalls[] = ['listId' => $listId, 'member' => $member];

        return md5(strtolower($member->emailAddress));
    }

    #[\Override]
    public function getMember(string $listId, string $subscriberHash): ?Member
    {
        return null;
    }

    #[\Override]
    public function removeMember(string $listId, string $subscriberHash): void
    {
    }

    #[\Override]
    public function getMemberTags(string $listId, string $subscriberHash): array
    {
        return [];
    }

    #[\Override]
    public function updateMemberTags(string $listId, string $subscriberHash, array $tags): void
    {
    }

    #[\Override]
    public function upsertStore(string $storeId, Store $store): void
    {
    }

    #[\Override]
    public function removeStore(string $storeId): void
    {
    }

    #[\Override]
    public function upsertProduct(string $storeId, Product $product): void
    {
    }

    #[\Override]
    public function removeProduct(string $storeId, string $productId): void
    {
    }

    #[\Override]
    public function upsertCart(string $storeId, Cart $cart): void
    {
        self::$upsertCartCalls[] = ['storeId' => $storeId, 'cart' => $cart];
    }

    #[\Override]
    public function removeCart(string $storeId, string $cartId): void
    {
        self::$removeCartCalls[] = ['storeId' => $storeId, 'cartId' => $cartId];
    }

    #[\Override]
    public function upsertOrder(string $storeId, Order $order): void
    {
        self::$upsertOrderCalls[] = ['storeId' => $storeId, 'order' => $order];
    }

    #[\Override]
    public function removeOrder(string $storeId, string $orderId): void
    {
        self::$removeOrderCalls[] = ['storeId' => $storeId, 'orderId' => $orderId];
    }

    #[\Override]
    public function upsertEcommerceCustomer(string $storeId, EcommerceCustomer $customer): void
    {
    }

    #[\Override]
    public function ping(): void
    {
    }

    #[\Override]
    public function getAudiences(): array
    {
        return [];
    }
}
