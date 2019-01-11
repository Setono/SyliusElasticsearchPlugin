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
final class OptionBuilder extends AbstractBuilder
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
                $this->resolveProductOptions($product, $document);
            });
    }

    /**
     * @param ProductInterface $product
     * @param Document         $document
     */
    private function resolveProductOptions(ProductInterface $product, Document $document): void
    {
        foreach ($product->getVariants() as $productVariant) {
            foreach ($productVariant->getOptionValues() as $productOptionValue) {
                if (empty($productOptionValue->getValue())) {
                    continue;
                }
                $index = 'option_' . $productOptionValue->getOption()->getCode();
                $options = $document->has($index) ? $document->get($index) : [];
                $value = $this->stringFormatter->formatToLowercaseWithoutSpaces($productOptionValue->getValue());
                $options[] = $value;

                $document->set($index, array_values(array_unique($options)));
            }
        }
    }
}
