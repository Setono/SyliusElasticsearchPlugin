<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\PreTransformEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionTranslationInterface;
use Sylius\Component\Product\Model\ProductOptionValueTranslationInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class OptionBuilder extends AbstractBuilder
{
    public function consumeEvent(PreTransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $this->resolveProductOptions($product, $document);
            });
    }

    private function resolveProductOptions(ProductInterface $product, Document $document): void
    {
        $options = [];
        /** @var ProductVariantInterface $productVariant */
        foreach ($product->getVariants() as $productVariant) {
            foreach ($productVariant->getOptionValues() as $productOptionValue) {
                if (empty($productOptionValue->getValue())) {
                    continue;
                }

                $translations = [];
                /** @var ProductOptionTranslationInterface $translation */
                foreach ($productOptionValue->getOption()->getTranslations() as $translation) {
                    $translations[] = [
                        'locale' => $translation->getLocale(),
                        'name' => $translation->getName(),
                    ];
                }

                $option = [
                    'id' => $productOptionValue->getOption()->getId(),
                    'code' => $productOptionValue->getOption()->getCode(),
                    'translations' => $translations,
                    'value' => [],
                    'onHand' => $productVariant->isTracked() ? (int) $productVariant->getOnHand() - (int) $productVariant->getOnHold() : 1,
                ];

                /** @var ProductOptionValueTranslationInterface $translation */
                foreach ($productOptionValue->getTranslations() as $translation) {
                    $option['value'][] = [
                        'code' => $productOptionValue->getCode(),
                        'locale' => $translation->getLocale(),
                        'name' => $translation->getValue(),
                    ];
                }

                $options[] = $option;
            }
        }

        if (!empty($options)) {
            $document->set('options', $options);
        }
    }
}
