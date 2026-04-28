<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Member;

final class MemberSubscriptionUpdate
{
    public function __construct(
        public readonly string $type,
        public readonly string $email,
        public readonly string $listId,
    ) {
    }
}
