<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Locale\Context\LocaleContextInterface;

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
            }
        );
    }
}
