<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

/**
 * @author jdk
 */
trait ProductAttributeValueRepositoryTrait
{
    /**
     * @param string $attributeCode
     * @param string $locale
     * @param string $type
     *
     * @return array
     */
    public function findValuesByAttributeCode(string $attributeCode, string $locale, string $type = 'text'): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.' . $type)
            ->join('p.attribute', 'pa')
            ->andWhere('p.localeCode = :locale')
            ->andWhere('pa.code = :attributeCode')
            ->setParameter('attributeCode', $attributeCode)
            ->setParameter('locale', $locale)
            ->groupBy('p.' . $type)
            ->getQuery()
            ->getResult();
    }
}
