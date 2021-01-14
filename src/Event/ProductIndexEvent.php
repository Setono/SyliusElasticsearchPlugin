<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Event;

use Pagerfanta\Pagerfanta;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductIndexEvent
{
    /** @var Pagerfanta */
    private $results;

    /** @var TaxonInterface */
    private $taxon;

    public function __construct(Pagerfanta $results, TaxonInterface $taxon)
    {
        $this->results = $results;
        $this->taxon = $taxon;
    }

    public function getResults(): Pagerfanta
    {
        return $this->results;
    }

    public function getTaxon(): TaxonInterface
    {
        return $this->taxon;
    }
}
