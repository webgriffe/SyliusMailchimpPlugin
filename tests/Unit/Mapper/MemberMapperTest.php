<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Event\MemberMappedEvent;
use Webgriffe\SyliusMailchimpPlugin\Exception\MissingCustomerEmailException;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapper;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver;
use Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MemberMapperTest extends TestCase
{
    private MockObject&MemberStatusResolverInterface $statusResolver;

    private MockObject&TagsResolverInterface $tagsResolver;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MemberMapper $mapper;

    protected function setUp(): void
    {
        $this->statusResolver = $this->createMock(MemberStatusResolverInterface::class);
        $this->tagsResolver = $this->createMock(TagsResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $mergeFieldsProvider = $this->createMock(MergeFieldsProviderInterface::class);
        $mergeFieldsProvider->method('provide')->willReturn(['FNAME' => 'John', 'LNAME' => 'Doe']);

        $mergeFieldsResolver = new MergeFieldsResolver([$mergeFieldsProvider]);

        $this->mapper = new MemberMapper(
            $this->statusResolver,
            $mergeFieldsResolver,
            $this->tagsResolver,
            $this->eventDispatcher,
        );
    }

    public function test_throws_exception_when_customer_has_no_email(): void
    {
        $this->expectException(MissingCustomerEmailException::class);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(null);
        $customer->method('getId')->willReturn(42);

        $this->mapper->map($customer, 'list-id');
    }

    public function test_throws_exception_when_customer_email_is_empty(): void
    {
        $this->expectException(MissingCustomerEmailException::class);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('');
        $customer->method('getId')->willReturn(42);

        $this->mapper->map($customer, 'list-id');
    }

    public function test_maps_customer_to_member(): void
    {
        $this->statusResolver->method('resolve')->willReturn('subscribed');
        $this->tagsResolver->method('resolve')->willReturn([]);
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');

        $member = $this->mapper->map($customer, 'list-abc');

        $this->assertSame('john@example.com', $member->emailAddress);
        $this->assertSame('subscribed', $member->status);
        $this->assertSame('John', $member->mergeFields->firstName);
        $this->assertSame('Doe', $member->mergeFields->lastName);
    }

    public function test_dispatches_member_mapped_event(): void
    {
        $this->statusResolver->method('resolve')->willReturn('subscribed');
        $this->tagsResolver->method('resolve')->willReturn([]);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(MemberMappedEvent::class))
            ->willReturnArgument(0);

        $this->mapper->map($customer, 'list-abc');
    }

    public function test_member_can_be_modified_by_event_listener(): void
    {
        $this->statusResolver->method('resolve')->willReturn('subscribed');
        $this->tagsResolver->method('resolve')->willReturn([]);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (MemberMappedEvent $event): MemberMappedEvent {
                $event->member = $event->member->withStatus('pending');

                return $event;
            },
        );

        $member = $this->mapper->map($customer, 'list-abc');

        $this->assertSame('pending', $member->status);
    }

    public function test_maps_tags_from_tags_resolver(): void
    {
        $this->statusResolver->method('resolve')->willReturn('subscribed');
        $this->tagsResolver->method('resolve')->willReturn(['VIP', 'Newsletter']);
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');

        $member = $this->mapper->map($customer, 'list-abc');

        $this->assertSame(['VIP', 'Newsletter'], $member->tags);
    }
}
