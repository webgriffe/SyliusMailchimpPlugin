<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Console\MailchimpConsoleCartContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Setup\MailchimpChannelContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Setup\MailchimpCustomerContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop\MailchimpCartContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop\MailchimpProfileContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop\MailchimpOrderContext;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop\MailchimpRegistrationContext;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncCartsCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;

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

    $services->set(MailchimpCustomerContext::class)
        ->args(
            [
                service('sylius.repository.customer'),
                service('doctrine.orm.entity_manager'),
            ]
        )
        ->public();

    $services->set(MailchimpProfileContext::class)
        ->args(
            [
                service(StubMailchimpClient::class),
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

    $services->set(MailchimpCartContext::class)
        ->args(
            [
                service(StubMailchimpClient::class),
            ]
        )
        ->public();

    $services->set(MailchimpConsoleCartContext::class)
        ->args(
            [
                service('kernel'),
                service(SyncCartsCommand::class),
            ]
        )
        ->public();

    $services->set(MailchimpOrderContext::class)
        ->args(
            [
                service(StubMailchimpClient::class),
                service('sylius.behat.shared_storage'),
                service('doctrine.orm.entity_manager'),
                service('event_dispatcher'),
                service('sylius.repository.customer'),
            ]
        )
        ->public();

    $services->alias(MailchimpClientInterface::class, StubMailchimpClient::class);
};
