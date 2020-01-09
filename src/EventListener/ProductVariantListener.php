<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use FOS\ElasticaBundle\Persister\ObjectPersister;
use Sylius\Component\Core\Model\ProductVariant;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

class ProductVariantListener
{
    /** @var ObjectPersister */
    private $persister;

    public function __construct(ObjectPersister $persister)
    {
        $this->persister = $persister;
    }

    public function postUpdate(ProductVariant $variant)
    {
        $onHand = 0;

        $product = $variant->getProduct();
        foreach($product->getVariants() as $child) {
            /** @var ProductVariant $child */
            $onHand += $child->getOnHand();
        }

        // As there is no way to test if the product is already exist in the ES index
        // we will remove the product from the index and add it again if onHand is greater than 0
        $this->persister->deleteOne($product);

        if ($onHand > 0) {
            $this->persister->insertOne($product);
        }
    }
}
