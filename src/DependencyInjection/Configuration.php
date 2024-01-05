<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_elasticsearch');
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress UndefinedMethod,PossiblyNullReference,MixedMethodCall */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('index_configs')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('type_name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('model_class')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('pagination')
                    ->min(1)
                    ->defaultValue(16)
                ->end()
                ->integerNode('max_filter_options')
                    ->min(1)
                    ->defaultValue(100)
                ->end()
                ->booleanNode('enable_product_variant_listener')
                    ->defaultValue(true)
                ->end()
                ->booleanNode('enable_product_taxon_listener')
                    ->defaultValue(true)
        ;

        return $treeBuilder;
    }
}
