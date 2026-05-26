<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client;

use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

interface MailchimpClientInterface
{
    // Members (audience)
    public function upsertMember(string $listId, Member $member): string;

    public function getMember(string $listId, string $subscriberHash): ?Member;

    public function removeMember(string $listId, string $subscriberHash): void;

    /** @return string[] */
    public function getMemberTags(string $listId, string $subscriberHash): array;

    /** @param string[] $tags */
    public function updateMemberTags(string $listId, string $subscriberHash, array $tags): void;

    // Stores (e-commerce)
    public function upsertStore(Store $store): void;

    public function removeStore(string $storeId): void;

    // Products
    public function upsertProduct(string $storeId, Product $product): void;

    public function removeProduct(string $storeId, string $productId): void;

    // Carts
    public function upsertCart(string $storeId, Cart $cart): void;

    public function removeCart(string $storeId, string $cartId): void;

    // Orders
    public function upsertOrder(string $storeId, Order $order): void;

    public function removeOrder(string $storeId, string $orderId): void;

    // Ecommerce Customers
    public function upsertEcommerceCustomer(string $storeId, EcommerceCustomer $customer): void;

    // Utility
    public function ping(): void;

    /** @return list<array{id: string, name: string}> */
    public function getAudiences(): array;
}
