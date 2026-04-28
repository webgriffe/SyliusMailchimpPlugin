<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Bundle\CoreBundle\Doctrine\ORM\CustomerRepository as BaseCustomerRepository;
use Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM\CustomerRepositoryTrait;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;

final class CustomerRepository extends BaseCustomerRepository implements MailchimpCustomerRepositoryInterface
{
    use CustomerRepositoryTrait;
}
