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
        ->arg('$apiKey', param('webgriffe_sylius_mailchimp.api_key'))
        ->arg('$storeMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapperInterface'))
        ->arg('$productMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapperInterface'))
        ->arg('$cartMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapperInterface'))
        ->arg('$orderMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapperInterface'))
        ->arg('$ecommerceCustomerMapper', service('Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapperInterface'));

    $services->alias(MailchimpClientInterface::class, MailchimpClient::class);
};
