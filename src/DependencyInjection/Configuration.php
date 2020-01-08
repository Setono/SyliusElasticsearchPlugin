<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('setono_sylius_elasticsearch');
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
                ->scalarNode('pagination')
                    ->defaultValue(16)
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('max_filter_options')
                    ->defaultValue(100)
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
