<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CustomerSubscriber;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomerSubscriber::class)
        ->arg('$memberEnqueuer', service(MemberEnqueuerInterface::class))
        ->arg('$audienceContext', service('Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface'))
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$entityManager', service('doctrine.orm.entity_manager'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->tag('kernel.event_subscriber');
};
