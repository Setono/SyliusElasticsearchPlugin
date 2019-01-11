<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder\Mapper;

use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
interface ProductTaxonsMapperInterface
{
    /**
     * Get a unique array of taxon codes associated with a product.
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function mapToUniqueCodes(ProductInterface $product): array;
}
