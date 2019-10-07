<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Doctrine\ORM\QueryBuilder;

interface ProductRepositoryInterface
{
    /**
     * @param string $alias
     * @param null   $indexBy
     *
     * @return QueryBuilder
     */
    public function createEnabledProductQueryBuilder($alias, $indexBy = null): QueryBuilder;
}
