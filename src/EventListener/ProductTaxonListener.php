<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Webmozart\Assert\Assert;

class ProductTaxonListener
{
    public function __construct(
        private readonly ObjectPersisterInterface $persister,
        private readonly bool $enabled,
    ) {
    }

    private function reindexProductIfEnabled(ProductTaxonInterface $productTaxon): void
    {
        if (!$this->enabled) {
            return;
        }

        $product = $productTaxon->getProduct();
        Assert::notNull($product);

        $this->persister->replaceOne($product);
    }

    public function postUpdate(ProductTaxonInterface $productTaxon): void
    {
        $this->reindexProductIfEnabled($productTaxon);
    }

    public function postPersist(ProductTaxonInterface $productTaxon): void
    {
        $this->reindexProductIfEnabled($productTaxon);
    }

    public function postRemove(ProductTaxonInterface $productTaxon): void
    {
        $this->reindexProductIfEnabled($productTaxon);
    }
}
