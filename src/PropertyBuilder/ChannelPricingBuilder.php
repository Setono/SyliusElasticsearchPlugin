<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ChannelPricingBuilder extends AbstractBuilder
{
    /**
     * {@inheritdoc}
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                if ($product->getVariants()->count() === 0) {
                    return;
                }

                /** @var ProductVariantInterface $productVariant */
                $productVariant = $product->getVariants()->first();

                foreach ($productVariant->getChannelPricings() as $channelPricing) {
                    $document->set('price', $channelPricing->getPrice());
                }
            });
    }
}
