<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Transformer;

use Elastica\Document;
use FOS\ElasticaBundle\Transformer\ModelToElasticaAutoTransformer;

class ProductTransformer extends ModelToElasticaAutoTransformer
{
    public function transform($object, array $fields): Document
    {
        $identifier = $this->propertyAccessor->getValue($object, $this->options['identifier']);
        if ($identifier && !is_scalar($identifier)) {
            $identifier = (string) $identifier;
        }

        return $this->transformObjectToDocument($object, [], $identifier);
    }
}
