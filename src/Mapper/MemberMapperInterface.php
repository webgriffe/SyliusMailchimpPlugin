<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

interface MemberMapperInterface
{
    public function map(CustomerInterface $customer, string $listId): Member;
}
