<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuer;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberEnqueuer::class)
        ->arg('$messageBus', service('messenger.default_bus'))
        ->arg('$audienceContext', service('Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface'))
        ->arg('$mailchimpClient', service('Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'));
};
