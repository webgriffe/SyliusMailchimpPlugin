<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer;

use Sylius\Component\Core\Model\CustomerInterface as BaseCustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;

interface CustomerInterface extends BaseCustomerInterface, MailchimpAwareInterface
{
}
