<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Transformer;

use Elastica\Document;
use FOS\ElasticaBundle\Transformer\ModelToElasticaAutoTransformer;

/** @psalm-suppress PropertyNotSetInConstructor */
class ProductTransformer extends ModelToElasticaAutoTransformer
{
    public function transform(object $object, array $fields): Document
    {
        /** @var mixed $identifier */
        $identifier = $this->propertyAccessor->getValue($object, $this->options['identifier']);

        if (!is_string($identifier)) {
            $identifier = (string) $identifier;
        }

        return $this->transformObjectToDocument($object, [], $identifier);
    }
}
