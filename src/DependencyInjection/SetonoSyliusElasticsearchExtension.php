<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\DependencyInjection;

use Setono\SyliusElasticsearchPlugin\Doctrine\ObjectChangeListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author jdk
 */
class SetonoSyliusElasticsearchExtension extends Extension
{
    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_elasticsearch.index_configs', $config['index_configs']);

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
