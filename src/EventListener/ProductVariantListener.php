<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

class ProductVariantListener
{
    public function __construct(
        private  readonly ObjectPersisterInterface $persister,
        private readonly bool $enabled,
    ) {
    }

    public function postUpdate(ProductVariantInterface $variant): void
    {
        if (!$this->enabled) {
            return;
        }

        $product = $variant->getProduct();
        Assert::notNull($product);

        foreach ($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if ($child->getOnHand() > 0) {
                $this->persister->replaceOne($product);

                return;
            }
        }

        $this->persister->deleteOne($product);
    }
}
