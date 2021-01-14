<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Sylius\Component\Product\Model\ProductAttributeValueInterface;

interface ProductAttributeValueRepositoryInterface
{
    /**
     * @return ProductAttributeValueInterface
     */
    public function findValuesByAttributeCode(string $attributeCode, string $locale): ?ProductAttributeValueInterface;
}
