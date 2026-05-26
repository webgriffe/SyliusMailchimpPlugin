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

/**
 * Tracks all calls in a temporary file so they are visible across processes
 * (e.g. PHP-FPM handling Chrome requests vs the Behat context runner).
 */
final class StubMailchimpClient implements MailchimpClientInterface
{
    private static function getCallsFile(): string
    {
        return sys_get_temp_dir() . '/mailchimp_stub_calls.ser';
    }

    private static function readCalls(): array
    {
        $file = self::getCallsFile();
        if (!file_exists($file)) {
            return self::emptyCallsArray();
        }

        $data = @unserialize((string) file_get_contents($file));

        return is_array($data) ? $data : self::emptyCallsArray();
    }

    private static function writeCalls(array $calls): void
    {
        $file = self::getCallsFile();
        $serialized = serialize($calls);
        file_put_contents($file, $serialized, \LOCK_EX);
        @chmod($file, 0666);
    }

    private static function emptyCallsArray(): array
    {
        return [
            'upsertMember' => [],
            'upsertCart' => [],
            'removeCart' => [],
            'upsertOrder' => [],
            'removeOrder' => [],
        ];
    }

    public function reset(): void
    {
        self::writeCalls(self::emptyCallsArray());
    }

    /** @return array<array{listId: string, member: Member}> */
    public function getUpsertMemberCalls(): array
    {
        return self::readCalls()['upsertMember'] ?? [];
    }

    /** @return array<array{storeId: string, cart: Cart}> */
    public function getUpsertCartCalls(): array
    {
        return self::readCalls()['upsertCart'] ?? [];
    }

    /** @return array<array{storeId: string, cartId: string}> */
    public function getRemoveCartCalls(): array
    {
        return self::readCalls()['removeCart'] ?? [];
    }

    /** @return array<array{storeId: string, order: Order}> */
    public function getUpsertOrderCalls(): array
    {
        return self::readCalls()['upsertOrder'] ?? [];
    }

    /** @return array<array{storeId: string, orderId: string}> */
    public function getRemoveOrderCalls(): array
    {
        return self::readCalls()['removeOrder'] ?? [];
    }

    public function getLastUpsertOrderCall(): ?Order
    {
        $calls = self::readCalls()['upsertOrder'] ?? [];
        if ($calls === []) {
            return null;
        }

        return $calls[array_key_last($calls)]['order'];
    }

    #[\Override]
    public function upsertMember(string $listId, Member $member): string
    {
        $calls = self::readCalls();
        $calls['upsertMember'][] = ['listId' => $listId, 'member' => $member];
        self::writeCalls($calls);

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
        $calls = self::readCalls();
        $calls['upsertCart'][] = ['storeId' => $storeId, 'cart' => $cart];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeCart(string $storeId, string $cartId): void
    {
        $calls = self::readCalls();
        $calls['removeCart'][] = ['storeId' => $storeId, 'cartId' => $cartId];
        self::writeCalls($calls);
    }

    #[\Override]
    public function upsertOrder(string $storeId, Order $order): void
    {
        $calls = self::readCalls();
        $calls['upsertOrder'][] = ['storeId' => $storeId, 'order' => $order];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeOrder(string $storeId, string $orderId): void
    {
        $calls = self::readCalls();
        $calls['removeOrder'][] = ['storeId' => $storeId, 'orderId' => $orderId];
        self::writeCalls($calls);
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
