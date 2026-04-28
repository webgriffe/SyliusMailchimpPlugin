<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order;

use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

interface OrderInterface extends BaseOrderInterface, MailchimpOrderAwareInterface
{
}
