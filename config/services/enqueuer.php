<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$audienceContext', service('Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface'))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'));
    $services->alias(MemberEnqueuerInterface::class, MemberEnqueuer::class);

    $services->set(StoreEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$logger', service('monolog.logger.mailchimp'));
    $services->alias(StoreEnqueuerInterface::class, StoreEnqueuer::class);

    $services->set(ProductEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$logger', service('monolog.logger.mailchimp'));
    $services->alias(ProductEnqueuerInterface::class, ProductEnqueuer::class);

    $services->set(CartEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$logger', service('monolog.logger.mailchimp'));
    $services->alias(CartEnqueuerInterface::class, CartEnqueuer::class);

    $services->set(OrderEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$logger', service('monolog.logger.mailchimp'));
    $services->alias(OrderEnqueuerInterface::class, OrderEnqueuer::class);
};

