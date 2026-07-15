<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberMapper::class)
        ->arg('$statusResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface'))
        ->arg('$mergeFieldsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver'))
        ->arg('$tagsResolver', service('Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface'))
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->alias(MemberMapperInterface::class, MemberMapper::class);

    $services->set(StoreMapper::class)
        ->arg('$storeIdentifierResolver', service(StoreIdentifierResolverInterface::class));

    $services->alias(StoreMapperInterface::class, StoreMapper::class);

    $services->set(EcommerceCustomerMapper::class);

    $services->alias(EcommerceCustomerMapperInterface::class, EcommerceCustomerMapper::class);

    $services->set(ProductVariantMapper::class)
        ->arg('$pricesCalculator', service(ProductVariantPricesCalculatorInterface::class))
        ->arg('$imagineFilterService', service('liip_imagine.service.filter'));

    $services->alias(ProductVariantMapperInterface::class, ProductVariantMapper::class);

    $services->set(ProductMapper::class)
        ->arg('$productVariantMapper', service(ProductVariantMapperInterface::class))
        ->arg('$router', service('router'))
        ->arg('$imagineFilterService', service('liip_imagine.service.filter'));

    $services->alias(ProductMapperInterface::class, ProductMapper::class);

    $services->set(CartMapper::class)
        ->arg('$customerMapper', service(EcommerceCustomerMapper::class));

    $services->alias(CartMapperInterface::class, CartMapper::class);

    $services->set(OrderMapper::class)
        ->arg('$customerMapper', service(EcommerceCustomerMapper::class));

    $services->alias(OrderMapperInterface::class, OrderMapper::class);
};
