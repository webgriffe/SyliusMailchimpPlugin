<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client\StubMailchimpClient;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Setup\MailchimpChannelContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop\MailchimpRegistrationContext;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;

return static function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->public();
    $services->set(StubMailchimpClient::class);

    $services->alias(MailchimpClientInterface::class, StubMailchimpClient::class);

    $services->set(MailchimpChannelContext::class)
        ->args(
            [
                service('sylius.behat.shared_storage'),
                service('doctrine.orm.entity_manager'),
            ]
        )
        ->public();

    $services->set(MailchimpRegistrationContext::class)
        ->args(
            [
                service(StubMailchimpClient::class),
            ]
        )
        ->public();

    $services->alias(MailchimpClientInterface::class, StubMailchimpClient::class);
};
