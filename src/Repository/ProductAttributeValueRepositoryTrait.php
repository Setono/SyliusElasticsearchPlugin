<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Doctrine\ORM\Query;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;

trait ProductAttributeValueRepositoryTrait
{
    public function findValuesByAttributeCode(string $attributeCode, string $locale): ?ProductAttributeValueInterface
    {
        /** @var Query $query */
        $query = $this->createQueryBuilder('p')
            ->join('p.attribute', 'pa')
            ->andWhere('p.localeCode = :locale')
            ->andWhere('pa.code = :attributeCode')
            ->setParameter('attributeCode', $attributeCode)
            ->setParameter('locale', $locale)
            ->getQuery();
        return $query->getOneOrNullResult();
    }
}
