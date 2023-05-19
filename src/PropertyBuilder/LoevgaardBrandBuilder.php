<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\PreTransformEvent;
use Loevgaard\SyliusBrandPlugin\Model\BrandAwareInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Support for LoevgaardSyliusBrandPlugin
 */
final class LoevgaardBrandBuilder extends AbstractBuilder
{
    public function consumeEvent(PreTransformEvent $event): void
    {
        $this->buildProperty(
            $event,
            ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                if ($product instanceof BrandAwareInterface) {
                    $brand = $product->getBrand();

                    if ($brand !== null) {
                        $document->set('brand', [
                            'code' => $brand->getCode(),
                            'name' => $brand->getName(),
                        ]);
                    } else {
                        $document->set('brand', []);
                    }
                }
            },
        );
    }
}
