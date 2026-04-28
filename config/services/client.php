<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MailchimpClient::class)
        ->arg('$httpClient', service('http_client'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->arg('$apiKey', param('webgriffe_sylius_mailchimp.api_key'));

    $services->alias(MailchimpClientInterface::class, MailchimpClient::class);
};
