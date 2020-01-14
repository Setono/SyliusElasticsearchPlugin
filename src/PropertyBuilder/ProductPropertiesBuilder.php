<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ProductPropertiesBuilder extends AbstractBuilder
{
    /**
     * {@inheritdoc}
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $document->set('id', $product->getId());
                $document->set('code', $product->getCode());
                $document->set('createdAt', $product->getCreatedAt()->format(DATE_ATOM));
                $document->set('position', $product->getPosition());
            }
        );
    }
}
