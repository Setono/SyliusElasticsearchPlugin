<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

class ProductVariantListener
{
    /** @var ObjectPersisterInterface */
    private $persister;

    public function __construct(ObjectPersisterInterface $persister)
    {
        $this->persister = $persister;
    }

    public function postUpdate(ProductVariantInterface $variant)
    {
        $onHand = 0;

        $product = $variant->getProduct();
        foreach($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
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
