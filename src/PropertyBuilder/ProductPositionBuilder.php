<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductPositionBuilder extends AbstractBuilder
{
    /**
     * @param TransformEvent $event
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                // Initialize positions with data for when showing the search result page that has no taxon selected
                $positions = [[
                    'taxonId' => 0,
                    'position' => $product->getPosition(),
                ]];

                foreach ($product->getProductTaxons() as $productTaxon) {
                    $positions[] = [
                        'taxonId' => $productTaxon->getTaxon()->getId(),
                        'position' => $productTaxon->getPosition(),
                    ];
                }

                $document->set('taxonPositions', $positions);
            }
        );
    }
}
