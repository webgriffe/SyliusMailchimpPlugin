<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedMethodCall
     */
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('webgriffe_sylius_mailchimp');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('api_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Mailchimp API key (e.g. abc123-us1)')
                ->end()
                ->enumNode('member_default_status')
                    ->values(['subscribed', 'pending'])
                    ->defaultValue('subscribed')
                    ->info('Default subscription status for new members: "subscribed" (single opt-in) or "pending" (double opt-in)')
                ->end()
                ->scalarNode('webhook_secret')
                    ->defaultValue('')
                    ->info('Secret token to validate incoming Mailchimp webhooks')
                ->end()
                ->booleanNode('send_unpaid_orders_as_carts')
                    ->defaultTrue()
                    ->info('Send unpaid/pending orders to Mailchimp as abandoned carts')
                ->end()
                ->arrayNode('mapper')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('product')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('image_type')
                                    ->defaultValue('main')
                                    ->info('Sylius product image type to use as Mailchimp product image')
                                ->end()
                                ->scalarNode('image_filter')
                                    ->defaultValue('sylius_medium')
                                    ->info('Liip Imagine filter to apply to product images (null = original URL)')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
