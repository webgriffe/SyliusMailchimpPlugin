<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\EcommerceCustomer;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\EcommerceCustomer\EcommerceCustomerEmailChange;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\EcommerceCustomer\EcommerceCustomerEmailChangeHandler;

final class EcommerceCustomerEmailChangeHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/EcommerceCustomer/EcommerceCustomerEmailChangeHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_it_removes_ecommerce_customer_from_configured_store(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'email-change@test.com']);

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);
        $handler(new EcommerceCustomerEmailChange($customer->getId()));

        $calls = $this->stub->getRemoveEcommerceCustomerCalls();
        self::assertCount(1, $calls);
        self::assertSame((string) $customer->getId(), $calls[0]['customerId']);
    }

    public function test_it_removes_open_carts_and_resets_mailchimp_cart_id(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'email-change@test.com']);
        $order = $em->getRepository(Order::class)->findOneBy(['customer' => $customer]);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);
        $handler(new EcommerceCustomerEmailChange($customer->getId()));

        self::assertCount(1, $this->stub->getRemoveCartCalls());
        $em->refresh($order);
        self::assertNull($order->getMailchimpCartId());
    }

    public function test_it_skips_channels_without_audience(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer_without_audience.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'email-change-no-audience@test.com']);

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);
        $handler(new EcommerceCustomerEmailChange($customer->getId()));

        self::assertCount(0, $this->stub->getRemoveEcommerceCustomerCalls());
    }

    public function test_it_skips_when_customer_not_found(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);
        $handler(new EcommerceCustomerEmailChange(99999));

        self::assertCount(0, $this->stub->getRemoveEcommerceCustomerCalls());
    }

    public function test_it_throws_on_transient_client_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'email-change@test.com']);
        $this->stub->failWith('removeEcommerceCustomer');

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);

        $this->expectException(ClientException::class);
        $handler(new EcommerceCustomerEmailChange($customer->getId()));
    }

    public function test_it_does_not_throw_on_permanent_client_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'email-change@test.com']);
        $this->stub->failWith('removeEcommerceCustomer', statusCode: 400);

        $handler = self::getContainer()->get(EcommerceCustomerEmailChangeHandler::class);
        $handler(new EcommerceCustomerEmailChange($customer->getId()));

        self::assertCount(0, $this->stub->getRemoveEcommerceCustomerCalls());
    }
}
