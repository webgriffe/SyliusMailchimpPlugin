<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Twig\MailchimpExtension;
use Webgriffe\SyliusMailchimpPlugin\Twig\MailchimpRuntime;
use Webgriffe\SyliusMailchimpPlugin\Twig\NewsletterFormRuntime;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MailchimpExtension::class)
        ->tag('twig.extension');

    $services->set(MailchimpRuntime::class)
        ->args([
            service('sylius.repository.customer'),
            service('sylius.repository.order'),
            param('webgriffe_sylius_mailchimp.api_key'),
        ])
        ->tag('twig.runtime');

    $services->set(NewsletterFormRuntime::class)
        ->args([service('form.factory')])
        ->tag('twig.runtime');
};
