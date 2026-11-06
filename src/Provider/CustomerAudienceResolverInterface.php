<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Sylius\Component\Core\Model\CustomerInterface;

interface CustomerAudienceResolverInterface
{
    public function resolveAudienceId(CustomerInterface $customer): ?string;
}
