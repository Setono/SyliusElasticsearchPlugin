<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Provider;

use FOS\ElasticaBundle\Provider\Indexable as FOSIndexable;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

class Indexable extends FOSIndexable
{
    /**
     * @param string $indexName
     * @param string $typeName
     * @param mixed  $object
     *
     * @return bool
     */
    public function isObjectIndexable($indexName, $typeName, $object)
    {
        // If object is product, we should count the onHand for all it's variant and mark the object as
        // indexable if any of the variants has a greater on-hand amount than 0
        if ($object instanceof ProductInterface) {
            /** @var ProductInterface $object */
            foreach ($object->getVariants() as $productVariant) {
                /** @var ProductVariantInterface $productVariant */
                if ($productVariant->getOnHand() > 0) {
                    return true;
                }
            }

            return false;
        }

        return parent::isObjectIndexable($indexName, $typeName, $object);
    }
}
