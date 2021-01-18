<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;

class ProductTaxonListener
{
    /** @var ObjectPersisterInterface */
    private $persister;

    /** @var bool */
    private $enabled;

    public function __construct(ObjectPersisterInterface $persister, bool $enabled)
    {
        $this->persister = $persister;
        $this->enabled = $enabled;
    }

    private function reindexProductIfEnabled(ProductTaxonInterface $productTaxon)
    {
        if (!$this->enabled) {
            return;
        }

        $product = $productTaxon->getProduct();
        $this->persister->replaceOne($product);
    }

    public function postUpdate(ProductTaxonInterface $productTaxon)
    {
        $this->reindexProductIfEnabled($productTaxon);
    }

    public function postPersist(ProductTaxonInterface $productTaxon)
    {
        $this->reindexProductIfEnabled($productTaxon);
    }

    public function postRemove(ProductTaxonInterface $productTaxon)
    {
        $this->reindexProductIfEnabled($productTaxon);
    }
}
