<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Model;

use Symfony\Component\HttpFoundation\Request;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\QueryString;
use Elastica\Query\Term;
use Elastica\Query\Terms;

/**
 * Query configuration object used to build an Elastica Query
 *
 * @author jdk
 */
class ElasticsearchQueryConfiguration
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $categoryCode;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @var string
     */
    private $channel;

    /**
     * @param Request $request
     * @param string  $query
     * @param string  $categoryCode
     * @param array   $attributes
     */
    public function __construct(Request $request, string $query = '', string $categoryCode = '', array $attributes = [])
    {
        $this->request = $request;
        $this->query = $query;
        $this->categoryCode = $categoryCode;
        $this->attributes = $attributes;
    }

    /**
     * @param string $channel
     */
    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * Get e Query object based on the configurations
     *
     * @return Query
     */
    public function getQuery(): Query
    {
        $boolQuery = new BoolQuery();

        // Search term filter
        if (!empty($this->query)) {
            $boolQuery->addMust(new QueryString('*' . $this->query . '*'));
        }

        // Category filter
        if ($this->categoryCode) {
            $taxonQuery = new Terms();
            $taxonQuery->setTerms('product_taxons', [$this->categoryCode]);
            $boolQuery->addMust($taxonQuery);
        }

        // Filter on product attributes
        if (!empty($this->attributes)) {
            foreach ($this->attributes as $attributeCode => $attributeValue) {
                if (!empty($attributeValue)) {
                    $termQuery = new Term();
                    $termQuery->setTerm('attribute_' . $attributeCode, strtolower($attributeValue));
                    $boolQuery->addMust($termQuery);
                }
            }
        }

        // Brand filter
        $brand = $this->request->get('brand', null);
        if ($brand) {
            $brandQuery = new Term();
            $brandQuery->setTerm('brand', $brand);
            $boolQuery->addMust($brandQuery);
        }

        // Channel filter
        if ($this->channel) {
            $channelQuery = new Terms();
            $channelQuery->setTerms('channel', [strtolower($this->channel)]);
            $boolQuery->addMust($channelQuery);
        }

        $query = new Query($boolQuery);

        $this->addSorting($query);

        return $query;
    }

    /**
     * Adds sorting to the query, if any is defined
     *
     * @param Query $query
     */
    private function addSorting(&$query)
    {
        $sorting = $this->request->get('sorting', []);
        if (!empty($sorting)) {
            $sortColumn = key($sorting);
            $direction = $sorting[$sortColumn];

            // For text types, sort in the keyword column instead.
            if (in_array($sortColumn, ['name'])) {
                $sortColumn = "$sortColumn.keyword";
            }

            $query->addSort([
                $sortColumn => ['order' => $direction],
            ]);
        }
    }
}
