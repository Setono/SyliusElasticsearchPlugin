<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Loevgaard\SyliusBrandPlugin\Entity\ProductTrait;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Support for LoevgaardSyliusBrandPlugin
 */
final class LoevgaardBrandBuilder extends AbstractBuilder
{
    /**
     * @var boolean
     */
    private $enabled = false;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['LoevgaardSyliusBrandPlugin'])) {
            $this->enabled = true;
        }
    }

    /**
     * @param TransformEvent $event
     */
    public function consumeEvent(TransformEvent $event): void
    {
        if ($this->enabled) {
            $this->buildProperty($event, ProductInterface::class,
                function (ProductInterface $product, Document $document): void {
                    if ($product instanceof ProductTrait) {
                        $brand = $product->getBrand();

                        $document->set('brand', $brand->getName());
                    }
                }
            );
        }
    }
}
