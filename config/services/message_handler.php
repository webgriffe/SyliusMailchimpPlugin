<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberSubscriptionUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Newsletter\NewsletterSubscribeHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreUpdateHandler;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberCreateHandler::class)
        ->arg('$customerRepository', service('sylius.repository.customer'))
        ->arg('$memberMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface'))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(MemberUpdateHandler::class)
        ->arg('$customerRepository', service('sylius.repository.customer'))
        ->arg('$memberMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface'))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(MemberRemoveHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(MemberSubscriptionUpdateHandler::class)
        ->arg('$updater', service('Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(NewsletterSubscribeHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$memberDefaultStatus', param('webgriffe_sylius_mailchimp.member_default_status'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(StoreCreateHandler::class)
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(StoreUpdateHandler::class)
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(StoreRemoveHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(ProductCreateHandler::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$productMapper', service(ProductMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(ProductUpdateHandler::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$productMapper', service(ProductMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(ProductRemoveHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(CartCreateHandler::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$cartMapper', service(CartMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(CartUpdateHandler::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$cartMapper', service(CartMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(CartRemoveHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(OrderCreateHandler::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$orderMapper', service(OrderMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(OrderUpdateHandler::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$orderMapper', service(OrderMapper::class))
        ->arg('$storeMapper', service(StoreMapper::class))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');

    $services->set(OrderRemoveHandler::class)
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('messenger.message_handler');
};

