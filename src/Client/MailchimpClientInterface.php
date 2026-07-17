<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

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
    public function upsertStore(Audience $audience): void;

    public function removeStore(string $storeId): void;

    // Products
    public function upsertProduct(string $storeId, ProductInterface $product, ChannelInterface $channel, string $locale): void;

    public function removeProduct(string $storeId, string $productId): void;

    // Carts
    public function upsertCart(string $storeId, OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): void;

    public function removeCart(string $storeId, string $cartId): void;

    // Orders
    public function upsertOrder(string $storeId, OrderInterface&MailchimpOrderAwareInterface $order, bool $isInRealTime = false): void;

    public function removeOrder(string $storeId, string $orderId): void;

    // Ecommerce Customers
    public function upsertEcommerceCustomer(string $storeId, OrderInterface $order): void;

    public function removeEcommerceCustomer(string $storeId, string $customerId): void;

    // Utility
    public function ping(): void;

    /** @return list<array{id: string, name: string}> */
    public function getAudiences(): array;
}
