<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

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
            'removeMember' => [],
            'upsertCart' => [],
            'removeCart' => [],
            'upsertOrder' => [],
            'removeOrder' => [],
            'upsertStore' => [],
            'removeStore' => [],
            'upsertProduct' => [],
            'removeProduct' => [],
            'upsertEcommerceCustomer' => [],
            'removeEcommerceCustomer' => [],
            'throwOn' => [],
        ];
    }

    /**
     * Configures the stub to throw when the given client method is called.
     * Cleared by reset().
     */
    public function failWith(string $method, string $type = 'client', string $message = 'Simulated Mailchimp failure', int $statusCode = 500): void
    {
        $calls = self::readCalls();
        $calls['throwOn'][$method] = ['type' => $type, 'message' => $message, 'statusCode' => $statusCode];
        self::writeCalls($calls);
    }

    private function maybeThrow(string $method): void
    {
        $config = self::readCalls()['throwOn'][$method] ?? null;
        if ($config === null) {
            return;
        }

        if ($config['type'] === 'compliance') {
            throw ComplianceStateException::forEmail($config['message']);
        }

        throw ClientException::fromResponse($config['statusCode'] ?? 500, $config['message']);
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

    /** @return array<array{listId: string, subscriberHash: string}> */
    public function getRemoveMemberCalls(): array
    {
        return self::readCalls()['removeMember'] ?? [];
    }

    /** @return array<array{storeId: string, order: OrderInterface}> */
    public function getUpsertCartCalls(): array
    {
        return self::readCalls()['upsertCart'] ?? [];
    }

    /** @return array<array{storeId: string, cartId: string}> */
    public function getRemoveCartCalls(): array
    {
        return self::readCalls()['removeCart'] ?? [];
    }

    /** @return array<array{storeId: string, order: OrderInterface&MailchimpOrderAwareInterface}> */
    public function getUpsertOrderCalls(): array
    {
        return self::readCalls()['upsertOrder'] ?? [];
    }

    /** @return array<array{storeId: string, orderId: string}> */
    public function getRemoveOrderCalls(): array
    {
        return self::readCalls()['removeOrder'] ?? [];
    }

    /** @return array<array{audience: Audience}> */
    public function getUpsertStoreCalls(): array
    {
        return self::readCalls()['upsertStore'] ?? [];
    }

    /** @return array<array{storeId: string}> */
    public function getRemoveStoreCalls(): array
    {
        return self::readCalls()['removeStore'] ?? [];
    }

    /** @return array<array{storeId: string, product: ProductInterface, channel: ChannelInterface, locale: string}> */
    public function getUpsertProductCalls(): array
    {
        return self::readCalls()['upsertProduct'] ?? [];
    }

    /** @return array<array{storeId: string, productId: string}> */
    public function getRemoveProductCalls(): array
    {
        return self::readCalls()['removeProduct'] ?? [];
    }

    /** @return array<array{storeId: string, order: OrderInterface}> */
    public function getUpsertEcommerceCustomerCalls(): array
    {
        return self::readCalls()['upsertEcommerceCustomer'] ?? [];
    }

    /** @return array<array{storeId: string, customerId: string}> */
    public function getRemoveEcommerceCustomerCalls(): array
    {
        return self::readCalls()['removeEcommerceCustomer'] ?? [];
    }

    public function getLastUpsertOrderCall(): null|(OrderInterface&MailchimpOrderAwareInterface)
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
        $this->maybeThrow('upsertMember');
        $calls = self::readCalls();
        $calls['upsertMember'][] = ['listId' => $listId, 'member' => $member];
        self::writeCalls($calls);

        return md5(strtolower($member->emailAddress));
    }

    #[\Override]
    public function getMember(string $listId, string $subscriberHash): ?Member
    {
        $this->maybeThrow('getMember');

        return null;
    }

    #[\Override]
    public function removeMember(string $listId, string $subscriberHash): void
    {
        $this->maybeThrow('removeMember');
        $calls = self::readCalls();
        $calls['removeMember'][] = ['listId' => $listId, 'subscriberHash' => $subscriberHash];
        self::writeCalls($calls);
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
    public function upsertStore(Audience $audience): void
    {
        $this->maybeThrow('upsertStore');
        $calls = self::readCalls();
        $calls['upsertStore'][] = ['audience' => $audience];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeStore(string $storeId): void
    {
        $this->maybeThrow('removeStore');
        $calls = self::readCalls();
        $calls['removeStore'][] = ['storeId' => $storeId];
        self::writeCalls($calls);
    }

    #[\Override]
    public function upsertProduct(string $storeId, ProductInterface $product, ChannelInterface $channel, string $locale): void
    {
        $this->maybeThrow('upsertProduct');
        $calls = self::readCalls();
        $calls['upsertProduct'][] = ['storeId' => $storeId, 'product' => $product, 'channel' => $channel, 'locale' => $locale];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeProduct(string $storeId, string $productId): void
    {
        $this->maybeThrow('removeProduct');
        $calls = self::readCalls();
        $calls['removeProduct'][] = ['storeId' => $storeId, 'productId' => $productId];
        self::writeCalls($calls);
    }

    #[\Override]
    public function upsertCart(string $storeId, OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): void
    {
        $this->maybeThrow('upsertCart');
        $calls = self::readCalls();
        $calls['upsertCart'][] = ['storeId' => $storeId, 'order' => $order];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeCart(string $storeId, string $cartId): void
    {
        $this->maybeThrow('removeCart');
        $calls = self::readCalls();
        $calls['removeCart'][] = ['storeId' => $storeId, 'cartId' => $cartId];
        self::writeCalls($calls);
    }

    #[\Override]
    public function upsertOrder(string $storeId, OrderInterface&MailchimpOrderAwareInterface $order, bool $isInRealTime = false): void
    {
        $this->maybeThrow('upsertOrder');
        $calls = self::readCalls();
        $calls['upsertOrder'][] = ['storeId' => $storeId, 'order' => $order];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeOrder(string $storeId, string $orderId): void
    {
        $this->maybeThrow('removeOrder');
        $calls = self::readCalls();
        $calls['removeOrder'][] = ['storeId' => $storeId, 'orderId' => $orderId];
        self::writeCalls($calls);
    }

    #[\Override]
    public function upsertEcommerceCustomer(string $storeId, OrderInterface $order): void
    {
        $this->maybeThrow('upsertEcommerceCustomer');
        $calls = self::readCalls();
        $calls['upsertEcommerceCustomer'][] = ['storeId' => $storeId, 'order' => $order];
        self::writeCalls($calls);
    }

    #[\Override]
    public function removeEcommerceCustomer(string $storeId, string $customerId): void
    {
        $this->maybeThrow('removeEcommerceCustomer');
        $calls = self::readCalls();
        $calls['removeEcommerceCustomer'][] = ['storeId' => $storeId, 'customerId' => $customerId];
        self::writeCalls($calls);
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
