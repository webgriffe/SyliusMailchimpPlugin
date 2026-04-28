<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberUpdateHandler;

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
};
