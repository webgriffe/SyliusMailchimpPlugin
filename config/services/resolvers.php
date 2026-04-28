<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContext;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\ChannelAudienceProvider;
use Webgriffe\SyliusMailchimpPlugin\Resolver\FnameLnameMergeFieldsProvider;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolver;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver;
use Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolver;
use Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolverInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Audience
    $services->set(ChannelAudienceProvider::class);
    $services->alias(AudienceProviderInterface::class, ChannelAudienceProvider::class);

    $services->set(AudienceContext::class)
        ->arg('$channelContext', service('sylius.context.channel'))
        ->arg('$audienceProvider', service(AudienceProviderInterface::class));
    $services->alias(AudienceContextInterface::class, AudienceContext::class);

    // Member status
    $services->set(MemberStatusResolver::class)
        ->arg('$defaultStatus', param('webgriffe_sylius_mailchimp.member_default_status'));
    $services->alias(MemberStatusResolverInterface::class, MemberStatusResolver::class);

    // Merge fields
    $services->set(MergeFieldsResolver::class)
        ->arg('$providers', tagged_iterator('webgriffe_sylius_mailchimp.merge_fields_provider'));

    $services->set(FnameLnameMergeFieldsProvider::class)
        ->tag('webgriffe_sylius_mailchimp.merge_fields_provider');
    $services->alias(MergeFieldsProviderInterface::class, FnameLnameMergeFieldsProvider::class);

    // Tags
    $services->set(TagsResolver::class);
    $services->alias(TagsResolverInterface::class, TagsResolver::class);
};
