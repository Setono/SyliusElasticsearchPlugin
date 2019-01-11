<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Setono\SyliusElasticsearchPlugin\Formatter\StringFormatterInterface;
use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class AttributeBuilder extends AbstractBuilder
{
    /**
     * @var StringFormatterInterface
     */
    private $stringFormatter;

    /**
     * @param StringFormatterInterface $stringFormatter
     */
    public function __construct(StringFormatterInterface $stringFormatter)
    {
        $this->stringFormatter = $stringFormatter;
    }

    /**
     * @param TransformEvent $event
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $this->resolveProductAttributes($product, $document);
            });
    }

    /**
     * @param ProductInterface $product
     * @param Document         $document
     */
    private function resolveProductAttributes(ProductInterface $product, Document $document): void
    {
        foreach ($product->getAttributes() as $attributeValue) {
            $index = $attributeValue->getAttribute()->getCode();
            $value = $attributeValue->getValue();
            $attributes = [];
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $singleElement) {
                    $attributes[] = $this->stringFormatter->formatToLowercaseWithoutSpaces((string) $singleElement);
                }
            } else {
                $value = is_string($value) ? $this->stringFormatter->formatToLowercaseWithoutSpaces($value) : $value;
                $attributes[] = $value;
            }

            $document->set('attribute_' . $index, $attributes);
        }
    }
}
