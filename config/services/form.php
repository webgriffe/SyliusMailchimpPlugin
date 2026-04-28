<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusMailchimpPlugin\Form\Extension\ChannelTypeExtension;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ChannelTypeExtension::class)
        ->tag('form.type_extension');

    $services->set(NewsletterSubscribeType::class)
        ->tag('form.type');
};
