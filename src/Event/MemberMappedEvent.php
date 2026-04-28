<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Event;

use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

final class MemberMappedEvent
{
    public function __construct(
        public readonly CustomerInterface $customer,
        public readonly string $listId,
        public Member $member,
    ) {
    }
}
