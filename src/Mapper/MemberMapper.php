<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Psr\EventDispatcher\EventDispatcherInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Event\MemberMappedEvent;
use Webgriffe\SyliusMailchimpPlugin\Exception\MissingCustomerEmailException;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver;
use Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

final class MemberMapper implements MemberMapperInterface
{
    public function __construct(
        private readonly MemberStatusResolverInterface $statusResolver,
        private readonly MergeFieldsResolver $mergeFieldsResolver,
        private readonly TagsResolverInterface $tagsResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function map(CustomerInterface $customer, string $listId): Member
    {
        $email = $customer->getEmail();
        if ($email === null || $email === '') {
            /** @psalm-suppress MixedAssignment */
            $customerId = $customer->getId();

            throw MissingCustomerEmailException::forCustomerId(is_int($customerId) ? $customerId : 0);
        }

        $status = $this->statusResolver->resolve($customer);
        $mergeFields = $this->mergeFieldsResolver->resolve($customer);
        $tags = $this->tagsResolver->resolve($customer);

        $member = new Member($email, $status, $mergeFields, $tags);

        $event = new MemberMappedEvent($customer, $listId, $member);
        $this->eventDispatcher->dispatch($event);

        return $event->member;
    }
}
