<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\CustomerAudienceResolver;

final class CustomerAudienceResolverTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../DataFixtures/ORM/resources/Provider/CustomerAudienceResolverTest';

    private MockObject&ChannelContextInterface $channelContext;

    private EntityManagerInterface $em;

    private CustomerAudienceResolver $resolver;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var LoaderInterface $fixtureLoader */
        $fixtureLoader = $container->get('fidry_alice_data_fixtures.doctrine.loader');
        $fixtureLoader->load([self::FIXTURE_BASE_DIR . '/orders.yaml']);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->resolver = new CustomerAudienceResolver(
            $this->channelContext,
            $container->get(AudienceProviderInterface::class),
            $container->get('sylius.repository.order'),
            new NullLogger(),
        );
    }

    public function test_it_uses_the_current_channel_audience(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->channel('AUDIENCE_RESOLVER_A'));

        self::assertSame('audience-a', $this->resolver->resolveAudienceId($this->customer('audience-resolver-no-orders@test.com')));
    }

    public function test_it_falls_back_to_the_channel_of_the_latest_completed_order_without_channel_context(): void
    {
        $this->channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        self::assertSame('audience-b', $this->resolver->resolveAudienceId($this->customer('audience-resolver@test.com')));
    }

    public function test_it_returns_null_without_channel_context_and_orders(): void
    {
        $this->channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        self::assertNull($this->resolver->resolveAudienceId($this->customer('audience-resolver-no-orders@test.com')));
    }

    public function test_it_returns_null_when_the_channel_has_no_audience(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->channel('AUDIENCE_RESOLVER_NONE'));

        self::assertNull($this->resolver->resolveAudienceId($this->customer('audience-resolver@test.com')));
    }

    private function channel(string $code): ChannelInterface
    {
        $channel = $this->em->getRepository(Channel::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private function customer(string $email): Customer
    {
        $customer = $this->em->getRepository(Customer::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Customer::class, $customer);

        return $customer;
    }
}
