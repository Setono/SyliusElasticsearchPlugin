<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        if (method_exists(TreeBuilder::class, 'getRootNode')) {
            $treeBuilder = new TreeBuilder('setono_sylius_elasticsearch');
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $treeBuilder = new TreeBuilder();
            $rootNode = $treeBuilder->root('setono_sylius_elasticsearch');
        }
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('index_configs')
                    ->prototype('array')
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
                ->end()
            ->end();

        return $treeBuilder;
    }
}
