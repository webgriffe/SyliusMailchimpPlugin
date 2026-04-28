<?php

declare(strict_types=1);

use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Menu\AdminMenuListener;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AdminMenuListener::class)
        ->tag('kernel.event_listener', [
            'event' => MainMenuBuilder::EVENT_NAME,
            'method' => 'addMailchimpMenuItems',
        ]);
};
