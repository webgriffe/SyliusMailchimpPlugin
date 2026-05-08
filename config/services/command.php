<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Command\SyncAllCommand;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncCartsCommand;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncMembersCommand;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncOrdersCommand;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncProductsCommand;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncStoresCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpProductRepositoryInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SyncMembersCommand::class)
        ->arg('$customerRepository', service('sylius.repository.customer'))
        ->arg('$memberEnqueuer', service(MemberEnqueuerInterface::class))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->tag('console.command');

    $services->set(SyncStoresCommand::class)
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$storeEnqueuer', service(StoreEnqueuerInterface::class))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->tag('console.command');

    $services->set(SyncProductsCommand::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$productEnqueuer', service(ProductEnqueuerInterface::class))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->tag('console.command');

    $services->set(SyncCartsCommand::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$cartEnqueuer', service(CartEnqueuerInterface::class))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('console.command');

    $services->set(SyncOrdersCommand::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$orderEnqueuer', service(OrderEnqueuerInterface::class))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('console.command');

    $services->set(SyncAllCommand::class)
        ->tag('console.command');
};
