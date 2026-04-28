<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberMapper::class)
        ->arg('$statusResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface'))
        ->arg('$mergeFieldsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver'))
        ->arg('$tagsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface'))
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->alias(MemberMapperInterface::class, MemberMapper::class);

    $services->set(StoreMapper::class);

    $services->set(EcommerceCustomerMapper::class);

    $services->set(ProductVariantMapper::class);

    $services->set(ProductMapper::class)
        ->arg('$productVariantMapper', service(ProductVariantMapper::class));

    $services->set(CartMapper::class)
        ->arg('$customerMapper', service(EcommerceCustomerMapper::class));

    $services->set(OrderMapper::class)
        ->arg('$customerMapper', service(EcommerceCustomerMapper::class));
};
