<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;
use Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM\ProductRepositoryTrait;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpProductRepositoryInterface;

class ProductRepository extends BaseProductRepository implements MailchimpProductRepositoryInterface
{
    use ProductRepositoryTrait;
}
