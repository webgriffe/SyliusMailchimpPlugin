<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;
use Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM\OrderRepositoryTrait;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class OrderRepository extends BaseOrderRepository implements MailchimpOrderRepositoryInterface
{
    use OrderRepositoryTrait;
}
