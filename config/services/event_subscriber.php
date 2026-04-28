<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CustomerSubscriber;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\OrderSubscriber;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\ProductSubscriber;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomerSubscriber::class)
        ->arg('$memberEnqueuer', service(MemberEnqueuerInterface::class))
        ->arg('$audienceContext', service('Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface'))
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$entityManager', service('doctrine.orm.entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('kernel.event_subscriber');

    $services->set(OrderSubscriber::class)
        ->arg('$cartEnqueuer', service(CartEnqueuerInterface::class))
        ->arg('$orderEnqueuer', service(OrderEnqueuerInterface::class))
        ->arg('$sendUnpaidOrdersAsCarts', param('webgriffe_sylius_mailchimp.send_unpaid_orders_as_carts'))
        ->tag('kernel.event_subscriber');

    $services->set(ProductSubscriber::class)
        ->arg('$productEnqueuer', service(ProductEnqueuerInterface::class))
        ->tag('kernel.event_subscriber');
};

