<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Checker\OrderSyncEligibilityChecker;
use Webgriffe\SyliusMailchimpPlugin\Checker\OrderSyncEligibilityCheckerInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OrderSyncEligibilityChecker::class)
        ->arg('$sendUnpaidOrdersAsCarts', param('webgriffe_sylius_mailchimp.send_unpaid_orders_as_carts'));
    $services->alias(OrderSyncEligibilityCheckerInterface::class, OrderSyncEligibilityChecker::class);
};
