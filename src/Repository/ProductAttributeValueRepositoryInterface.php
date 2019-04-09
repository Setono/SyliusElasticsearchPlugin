<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Sylius\Component\Product\Model\ProductAttributeValueInterface;

interface ProductAttributeValueRepositoryInterface
{
    /**
     * @param string $attributeCode
     * @param string $locale
     *
     * @return ProductAttributeValueInterface
     */
    public function findValuesByAttributeCode(string $attributeCode, string $locale): ?ProductAttributeValueInterface;
}
