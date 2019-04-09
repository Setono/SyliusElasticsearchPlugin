<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Doctrine\ORM\QueryBuilder;

trait ProductRepositoryTrait
{
    public function createEnabledProductQueryBuilder($alias, $indexBy = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder($alias, $indexBy);
        $qb->andWhere($alias . '.enabled = 1');

        return $qb;
    }
}
