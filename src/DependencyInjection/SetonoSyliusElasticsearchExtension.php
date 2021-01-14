<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\DependencyInjection;

use function Safe\sprintf;
use Setono\SyliusElasticsearchPlugin\Doctrine\ObjectChangeListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SetonoSyliusElasticsearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_elasticsearch.index_configs', $config['index_configs']);
        $container->setParameter('setono_sylius_elasticsearch.pagination', $config['pagination']);
        $container->setParameter('setono_sylius_elasticsearch.max_filter_options', $config['max_filter_options']);
        $container->setParameter('setono_sylius_elasticsearch.enable_product_variant_listener', $config['enable_product_variant_listener']);
        $container->setParameter('setono_sylius_elasticsearch.enable_product_taxon_listener', $config['enable_product_taxon_listener']);

        foreach ($config['index_configs'] as $indexName => $indexConfigs) {
            $listenerId = sprintf(
                'elastic_search.object_change_listener.%s.%s',
                $indexName,
                $indexConfigs['type_name']
            );

            $container->register($listenerId, ObjectChangeListener::class)
                ->setPublic(true)
                ->addArgument($indexConfigs)
                ->addArgument('@fos_elastica.persister_registry')
                ->addArgument('@fos_elastica.indexable');
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
