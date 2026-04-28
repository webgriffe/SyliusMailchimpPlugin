<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Controller\WebhookController;
use Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberSubscriptionStatusUpdater::class)
        ->arg('$customerRepository', service('sylius.repository.customer'))
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'));

    $services->set(WebhookController::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->arg('$webhookSecret', param('webgriffe_sylius_mailchimp.webhook_secret'))
        ->tag('controller.service_arguments');
};
