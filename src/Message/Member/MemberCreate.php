<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Member;

final class MemberCreate
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $listId,
    ) {
    }
}
