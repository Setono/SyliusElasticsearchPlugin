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
        $product = $variant->getProduct();
        foreach($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if ($child->getOnHand() > 0) {
                $this->persister->replaceOne($product);
            }
        }

        $this->persister->deleteOne($product);
    }
}
