<?php

declare(strict_types=1);
/**
 * ElasticSearch index analysis settings
 */
$analysis = [
    'char_filter' => [
        'dash_and_hyphens' => [
            'type' => 'mapping',
            'mappings' => [
                '-=>',
            ],
        ],
    ],
    'filter' => [
        'ngram' => [
            'type' => 'ngram',
            'min_gram' => 3,
            'max_gram' => 3,
            'token_chars' => ['letter', 'digit'],
        ],
    ],
    'analyzer' => [
        'autocomplete' => [
            'type' => 'custom',
            'tokenizer' => 'standard',
            'char_filter' => 'dash_and_hyphens',
            'filter' => [
                'ngram',
                'lowercase',
                'asciifolding',
                'trim',
            ],
        ],
    ],
];

/**
 * Index settings for product models
 */
$productTypes = [
    'default' => [
        'properties' => [
            'description' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
            'shortDescription' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
            'metaKeywords' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
            'metaDescription' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
            'createdAt' => [
                'type' => 'date',
            ],
            'name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ],
        'persistence' => [
            'driver' => 'orm',
            'model' => '%sylius.model.product.class%',
            'provider' => [
                'query_builder_method' => 'createEnabledProductQueryBuilder',
            ],
            'listener' => [
                'enabled' => false,
            ],
            'elastica_to_model_transformer' => [
                'ignore_missing' => true,
            ],
        ],
    ],
];

/**
 * Index settings for taxon models
 */
$taxonTypes = [
    'default' => [
        'properties' => [
            'name' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
            'slug' => null,
            'description' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
            ],
        ],
        'persistence' => [
            'driver' => 'orm',
            'model' => '%sylius.model.taxon.class%',
            'provider' => null,
            'finder' => null,
            'listener' => [
                'enabled' => false,
            ],
        ],
    ],
];
