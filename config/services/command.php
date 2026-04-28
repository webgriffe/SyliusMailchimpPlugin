<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncMembersCommand;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SyncMembersCommand::class)
        ->arg('$customerRepository', service('sylius.repository.customer'))
        ->arg('$memberEnqueuer', service('Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface'))
        ->arg('$logger', service('monolog.logger.mailchimp'))
        ->arg('$commandLockEnable', param('webgriffe_sylius_mailchimp.command_lock_enable'))
        ->tag('console.command');
};
