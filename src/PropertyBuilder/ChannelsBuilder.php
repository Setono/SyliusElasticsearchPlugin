<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\PreTransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ChannelsBuilder extends AbstractBuilder
{
    public function consumeEvent(PreTransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $channels = [];

                foreach ($product->getChannels() as $channel) {
                    $channels[] = $channel->getCode();
                }

                $document->set('channels', $channels);
            }
        );
    }
}
