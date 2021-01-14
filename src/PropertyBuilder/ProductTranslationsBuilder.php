<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use Elastica\Document;
use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslation;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
final class ProductTranslationsBuilder extends AbstractBuilder
{
    /**
     * {@inheritdoc}
     */
    public function consumeEvent(TransformEvent $event): void
    {
        $this->buildProperty($event, ProductInterface::class,
            function (ProductInterface $product, Document $document): void {
                $this->resolveProductTranslations($product, $document);
            }
        );
    }

    private function resolveProductTranslations(ProductInterface $product, Document $document): void
    {
        $translations = [];

        /** @var ProductTranslation $translation */
        foreach ($product->getTranslations() as $translation) {
            $translations[] = [
                'locale' => $translation->getLocale(),
                'name' => $translation->getName(),
                'slug' => $translation->getSlug(),
                'description' => $translation->getDescription(),
                'shortDescription' => $translation->getShortDescription(),
                'metaKeywords' => $translation->getMetaKeywords(),
                'metaDescription' => $translation->getMetaDescription(),
            ];
        }

        $document->set('translations', $translations);
    }
}
