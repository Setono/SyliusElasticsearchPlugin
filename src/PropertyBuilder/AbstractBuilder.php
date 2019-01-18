<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
abstract class AbstractBuilder implements PropertyBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildProperty(TransformEvent $event, string $supportedModelClass, callable $callback): void
    {
        $model = $event->getObject();

        if (!$model instanceof $supportedModelClass) {
            return;
        }

        $document = $event->getDocument();

        $callback($model, $document);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TransformEvent::POST_TRANSFORM => 'consumeEvent',
        ];
    }

    /**
     * Get locale code from the index name on the document.
     * Fallback to current locale
     *
     * @param Document $document
     *
     * @return string
     */
    protected function getLocaleFromDocument(Document $document, LocaleContextInterface $localeContext)
    {
        $indexName = $document->getIndex();
        if (preg_match("/.*_(\w{2})_(\w{2})_products/", $indexName, $matches)) {
            return $matches[1] . '_' . strtoupper($matches[2]);
        }

        return $localeContext->getLocaleCode();
    }
}
