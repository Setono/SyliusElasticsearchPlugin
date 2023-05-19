<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\PreTransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductPositionBuilder extends AbstractBuilder
{
    public function consumeEvent(PreTransformEvent $event): void
    {
        $this->buildProperty(
            $event,
            ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $initialPosition = 0;
                if (method_exists($product, 'getPosition')) {
                    $initialPosition = (int) $product->getPosition();
                }

                // Initialize positions with data for when showing the search result page that has no taxon selected
                $positions = [[
                    'taxonId' => 0,
                    'position' => $initialPosition,
                ]];

                foreach ($product->getProductTaxons() as $productTaxon) {
                    $positions[] = [
                        'taxonId' => $productTaxon->getTaxon()?->getId(),
                        'position' => $productTaxon->getPosition(),
                    ];
                }

                $document->set('taxonPositions', $positions);
            },
        );
    }
}
