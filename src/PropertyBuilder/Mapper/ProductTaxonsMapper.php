<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder\Mapper;

use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ProductTaxonsMapper implements ProductTaxonsMapperInterface
{
    /**
     * {@inheritdoc}
     */
    public function mapToUniqueCodes(ProductInterface $product): array
    {
        $taxons = [];

        foreach ($product->getTaxons() as $taxon) {
            $taxons[] = $taxon->getCode();
        }

        return array_values(array_unique($taxons));
    }
}
