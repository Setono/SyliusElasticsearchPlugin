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

    /** @var bool */
    private $enabled;

    public function __construct(ObjectPersisterInterface $persister, bool $enabled)
    {
        $this->persister = $persister;
        $this->enabled = $enabled;
    }

    public function postUpdate(ProductVariantInterface $variant)
    {
        if (!$this->enabled) {
            return;
        }

        $product = $variant->getProduct();
        foreach($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if ($child->getOnHand() > 0) {
                $this->persister->replaceOne($product);
                return;
            }
        }

        $this->persister->deleteOne($product);
    }
}
