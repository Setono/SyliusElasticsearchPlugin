<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\PreTransformEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionTranslation;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class AttributeBuilder extends AbstractBuilder
{
    public function consumeEvent(PreTransformEvent $event): void
    {
        $this->buildProperty(
            $event,
            ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $this->resolveProductAttributes($product, $document);
            },
        );
    }

    private function resolveProductAttributes(ProductInterface $product, Document $document): void
    {
        $attributes = [];

        /** @var ProductAttributeValueInterface $attributeValue */
        foreach ($product->getAttributes()->getValues() as $attributeValue) {
            $translations = [];
            /** @var ProductOptionTranslation $translation */
            foreach ($attributeValue->getAttribute()?->getTranslations() ?? [] as $translation) {
                $translations[] = [
                    'locale' => $translation->getLocale(),
                    'name' => $translation->getName(),
                ];
            }

            $attribute = [
                'id' => $attributeValue->getAttribute()?->getId(),
                'code' => $attributeValue->getAttribute()?->getCode(),
                'locale' => $attributeValue->getLocaleCode(),
                'translations' => $translations,
            ];

            /** @var mixed $value */
            $value = $attributeValue->getValue();
            if (is_array($value)) {
                /** @var string|int $selectItem */
                foreach ($value as $selectItem) {
                    $configuration = $attributeValue->getAttribute()?->getConfiguration() ?? [];
                    if (!isset($configuration['choices']) || !is_array($configuration['choices']) || !isset($configuration['choices'][$selectItem]) || !is_array($configuration['choices'][$selectItem])) {
                        continue;
                    }

                    /**
                     * @var string $localeCode
                     * @var string $value
                     */
                    foreach ($configuration['choices'][$selectItem] as $localeCode => $value) {
                        $attribute['values'][] = [
                            'code' => $selectItem,
                            'locale' => $localeCode,
                            'name' => $value,
                        ];
                    }
                }
            } else {
                $attribute['values'][] = [
                    'code' => $value,
                    'locale' => $attributeValue->getLocaleCode(),
                    'name' => $value,
                ];
            }

            $attributes[] = $attribute;
        }

        $document->set('attributes', $attributes);
    }
}
