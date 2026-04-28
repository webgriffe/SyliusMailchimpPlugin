<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Controller\CartRecoveryController;
use Webgriffe\SyliusMailchimpPlugin\Controller\NewsletterController;
use Webgriffe\SyliusMailchimpPlugin\Controller\WebhookController;
use Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater;

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

    $services->set(NewsletterController::class)
        ->arg('$formFactory', service('form.factory'))
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$audienceContext', service('Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('controller.service_arguments');

    $services->set(CartRecoveryController::class)
        ->arg('$orderRepository', service('sylius.repository.order'))
        ->arg('$cartStorage', service('Sylius\Component\Core\Storage\CartStorageInterface'))
        ->arg('$channelContext', service('Sylius\Component\Channel\Context\ChannelContextInterface'))
        ->arg('$urlGenerator', service('router'))
        ->tag('controller.service_arguments');
};
