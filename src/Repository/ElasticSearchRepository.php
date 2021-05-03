<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Elastica\Aggregation\Filter as AggregationFilter;
use Elastica\Aggregation\Max as AggregationMax;
use Elastica\Aggregation\Min as AggregationMin;
use Elastica\Aggregation\Nested as AggregationNested;
use Elastica\Aggregation\Terms as AggregationTerms;
use Elastica\Aggregation\TopHits as AggregationTopHits;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Query\Nested;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class ElasticSearchRepository implements ElasticSearchRepositoryInterface
{
    /** @var BoolQuery */
    private $boolQuery;

    /** @var array */
    private $sort = [];

    /** @var int */
    private $currentTaxonId = 0;

    private $maxFilterOptions;

    public function __construct(int $maxFilterOptions)
    {
        $this->resetQuery();

        $this->maxFilterOptions = $maxFilterOptions;
    }

    public function whereChannel(ChannelInterface $channel): ElasticSearchRepositoryInterface
    {
        $this->boolQuery->addMust(new Term(['channels' => ['value' => $channel->getCode()]]));

        return $this;
    }

    public function whereTaxon(TaxonInterface $taxon): ElasticSearchRepositoryInterface
    {
        $this->currentTaxonId = $taxon->getId();
        $this->boolQuery->addMust(new Term(['taxons' => ['value' => $taxon->getId()]]));

        return $this;
    }

    public function whereEnabled(): ElasticSearchRepositoryInterface
    {
        $this->boolQuery->addMust(new Term(['enabled' => true]));

        return $this;
    }

    public function whereStock(): ElasticSearchRepositoryInterface
    {
        $this->boolQuery->addMust(new Range('stock', [
            'gte' => 1,
        ]));

        return $this;
    }

    public function whereBrands(array $brandCodes): ElasticSearchRepositoryInterface
    {
        $boolQuery = new BoolQuery();
        $boolQuery->addMust(new Terms('brand.code', $brandCodes));

        $nested = new Nested();
        $nested->setPath('brand');
        $nested->setQuery($boolQuery);
        $this->boolQuery->addMust($nested);

        return $this;
    }

    public function whereOptions(array $options): ElasticSearchRepositoryInterface
    {
        foreach ($options as $optionCode => $values) {
            if (!is_array($values)) {
                continue;
            }

            $optionBoolQuery = new BoolQuery();
            $optionBoolQuery->addMust(new Match('options.code', $optionCode));

            // Selected option needs to be in stock
            $optionBoolQuery->addMust(new Range('options.onHand', [
                'gt' => 0,
            ]));

            $valueBoolQuery = new BoolQuery();
            $valueBoolQuery->addMust(new Terms('options.value.code', $values));
            $valueNested = new Nested();
            $valueNested->setPath('options.value');
            $valueNested->setQuery($valueBoolQuery);
            $optionBoolQuery->addMust($valueNested);

            $optionNested = new Nested();
            $optionNested->setPath('options');
            $optionNested->setQuery($optionBoolQuery);
            $this->boolQuery->addMust($optionNested);
        }

        return $this;
    }

    public function whereAttributes(array $attributes, string $localeCode): ElasticSearchRepositoryInterface
    {
        foreach ($attributes as $attributesCode => $values) {
            if (!is_array($values)) {
                continue;
            }

            $optionBoolQuery = new BoolQuery();
            $optionBoolQuery->addMust(new Match('attributes.code', $attributesCode));
            $optionBoolQuery->addMust(new Match('attributes.locale', $localeCode));

            $valueBoolQuery = new BoolQuery();
            $valueBoolQuery->addMust(new Terms('attributes.values.code', $values));
            $valueNested = new Nested();
            $valueNested->setPath('attributes.values');
            $valueNested->setQuery($valueBoolQuery);
            $optionBoolQuery->addMust($valueNested);

            $optionNested = new Nested();
            $optionNested->setPath('attributes');
            $optionNested->setQuery($optionBoolQuery);
            $this->boolQuery->addMust($optionNested);
        }

        return $this;
    }

    public function whereChannelPrice(int $gte, int $lte, ChannelInterface $channel): ElasticSearchRepositoryInterface
    {
        $boolQuery = new BoolQuery();
        $boolQuery->addMust(new Match('prices.channel', $channel->getCode()));
        $boolQuery->addMust(new Range('prices.price', [
            'gte' => $gte,
            'lte' => $lte,
        ]));

        $nested = new Nested();
        $nested->setPath('prices');
        $nested->setQuery($boolQuery);
        $this->boolQuery->addMust($nested);

        return $this;
    }

    public function sortByPosition(): void
    {
        $this->sort[] = [
            'position' => [
                'order' => 'asc',
            ],
        ];
    }

    public function sortByCreated(string $direction): void
    {
        $this->sort[] = [
            'createdAt' => [
                'order' => $direction,
            ],
        ];
    }

    public function sortByProductName(string $direction, string $localeCode): void
    {
        $this->sort[] = [
            'translations.name.keyword' => [
                'order' => $direction,
                'nested' => [
                    'path' => 'translations',
                    'filter' => [
                        'match' => [
                            'translations.locale' => $localeCode,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function sortByPrice(string $direction, ChannelInterface $channel): void
    {
        $this->sort[] = [
            'prices.price' => [
                'order' => $direction,
                'nested' => [
                    'path' => 'prices',
                    'filter' => [
                        'term' => [
                            'prices.channel' => $channel->getCode(),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getAvailableFilters(ChannelInterface $channel, string $localeCode, TaxonInterface $taxon): Query
    {
        $query = $this->whereChannel($channel)
            ->whereTaxon($taxon)
            ->getQuery();

        $brandHitsAggregations = new AggregationTopHits('brand_hits');
        $brandHitsAggregations->setSize(1);
        $brandHitsAggregations->setSource(['includes' => ['brand.name', 'brand.code']]);
        $brandCodeAggregations = new AggregationTerms('code');
        $brandCodeAggregations->setField('brand.code');
        $brandCodeAggregations->setSize($this->maxFilterOptions);
        $brandCodeAggregations->addAggregation($brandHitsAggregations);
        $brandAggregations = new AggregationNested('brands', 'brand');
        $brandAggregations->addAggregation($brandCodeAggregations);
        $query->addAggregation($brandAggregations);

        $priceMinAggregation = new AggregationMin('min');
        $priceMinAggregation->setField('prices.price');
        $priceMaxAggregation = new AggregationMax('max');
        $priceMaxAggregation->setField('prices.price');
        $priceChannelAggregation = new AggregationFilter('channel', new Match('prices.channel', $channel->getCode()));
        $priceChannelAggregation->addAggregation($priceMinAggregation);
        $priceChannelAggregation->addAggregation($priceMaxAggregation);
        $priceAggregations = new AggregationNested('prices', 'prices');
        $priceAggregations->addAggregation($priceChannelAggregation);
        $query->addAggregation($priceAggregations);

        $optionsValueHitsAggregation = new AggregationTopHits('value_hits');
        $optionsValueHitsAggregation->setSize(1);
        $optionsValueHitsAggregation->setSource(['includes' => ['options.value.name', 'options.value.code']]);
        $optionsValueCodeAggregation = new AggregationTerms('value_code');
        $optionsValueCodeAggregation->setField('options.value.code');
        $optionsValueCodeAggregation->setSize($this->maxFilterOptions);
        $optionsValueCodeAggregation->addAggregation($optionsValueHitsAggregation);
        $optionsValueLocaleAggregation = new AggregationFilter('locale', new Match('options.value.locale', $localeCode));
        $optionsValueLocaleAggregation->addAggregation($optionsValueCodeAggregation);
        $optionsValueAggregation = new AggregationNested('value', 'options.value');
        $optionsValueAggregation->addAggregation($optionsValueLocaleAggregation);
        $optionsTypeAggregation = new AggregationTerms('type');
        $optionsTypeAggregation->setField('options.code');
        $optionsTypeAggregation->addAggregation($optionsValueAggregation);
        $optionsAggregation = new AggregationNested('options', 'options');
        $optionsAggregation->addAggregation($optionsTypeAggregation);
        $query->addAggregation($optionsAggregation);

        $attributesValueHitsAggregation = new AggregationTopHits('value_hits');
        $attributesValueHitsAggregation->setSize(1);
        $attributesValueHitsAggregation->setSource(['includes' => ['attributes.values.name', 'attributes.values.code']]);
        $attributesValueCodeAggregation = new AggregationTerms('values_code');
        $attributesValueCodeAggregation->setField('attributes.values.code');
        $attributesValueCodeAggregation->setSize($this->maxFilterOptions);
        $attributesValueCodeAggregation->addAggregation($attributesValueHitsAggregation);
        $attributesValueLocaleAggregation = new AggregationFilter('locale', new Match('attributes.values.locale', $localeCode));
        $attributesValueLocaleAggregation->addAggregation($attributesValueCodeAggregation);
        $attributesValueAggregation = new AggregationNested('value', 'attributes.values');
        $attributesValueAggregation->addAggregation($attributesValueLocaleAggregation);
        $attributesTypeAggregation = new AggregationTerms('type');
        $attributesTypeAggregation->setField('attributes.code');
        $attributesTypeAggregation->addAggregation($attributesValueAggregation);
        $attributesAggregation = new AggregationNested('attributes', 'attributes');
        $attributesAggregation->addAggregation($attributesTypeAggregation);
        $query->addAggregation($attributesAggregation);

        return $query;
    }

    public function resetQuery(): void
    {
        $this->boolQuery = new BoolQuery();
    }

    public function getQuery(bool $resetQueue = true): Query
    {
        $query = new Query($this->boolQuery);
        if (!empty($this->sort)) {
            $query->setSort($this->sort);
        }

        if ($resetQueue) {
            $this->resetQuery();
        }

        return $query;
    }
}
