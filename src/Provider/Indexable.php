<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Provider;

use FOS\ElasticaBundle\Provider\Indexable as FOSIndexable;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;

class Indexable extends FOSIndexable
{
    public function isObjectIndexable($indexName, $typeName, $object)
    {
        // If object is product, we should count the onHand for all it's variant and mark the object as non-indexable if it's onHand is 0
        if ($object instanceof Product) {
            /** @var Product $object */
            $onHand = 0;
            foreach ($object->getVariants() as $productVariant) {
                /** @var $productVariant ProductVariant */
                $onHand += $productVariant->getOnHand();
            }

            if ($onHand < 1) {
                return false;
            }
        }

        return parent::isObjectIndexable($indexName, $typeName, $object);
    }
}
