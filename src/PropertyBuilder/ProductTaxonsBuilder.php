<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Setono\SyliusElasticsearchPlugin\PropertyBuilder\Mapper\ProductTaxonsMapperInterface;
use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ProductTaxonsBuilder extends AbstractBuilder
{
    /**
     * @var ProductTaxonsMapperInterface
     */
    private $productTaxonsMapper;

    /**
     * @var string
     */
    private $taxonsProperty = 'product_taxons';

    /**
     * @param ProductTaxonsMapperInterface $productTaxonsMapper
     */
    public function __construct(ProductTaxonsMapperInterface $productTaxonsMapper)
    {
        $this->productTaxonsMapper = $productTaxonsMapper;
    }

    /**
     * @param TransformEvent $event
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $taxons = $this->productTaxonsMapper->mapToUniqueCodes($product);

                $document->set($this->taxonsProperty, $taxons);
            }
        );
    }
}
