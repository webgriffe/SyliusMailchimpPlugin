<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberMapper::class)
        ->arg('$statusResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface'))
        ->arg('$mergeFieldsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver'))
        ->arg('$tagsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface'))
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->alias(MemberMapperInterface::class, MemberMapper::class);
};
