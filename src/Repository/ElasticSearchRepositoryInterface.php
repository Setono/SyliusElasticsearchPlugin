<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Repository;

use Elastica\Query;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;

interface ElasticSearchRepositoryInterface
{
    public function whereChannel(ChannelInterface $channel): ElasticSearchRepositoryInterface;

    public function whereTaxon(TaxonInterface $taxon): ElasticSearchRepositoryInterface;

    public function whereEnabled(): ElasticSearchRepositoryInterface;

    public function whereStock(): ElasticSearchRepositoryInterface;

    public function whereBrands(array $brandCodes): ElasticSearchRepositoryInterface;

    public function whereOptions(array $options): ElasticSearchRepositoryInterface;

    public function whereAttributes(array $attributes, string $localeCode): ElasticSearchRepositoryInterface;

    public function whereChannelPrice(int $gte, int $lte, ChannelInterface $channel): ElasticSearchRepositoryInterface;

    public function sortByPosition(): void;

    public function sortByCreated(string $direction): void;

    public function sortByProductName(string $direction, string $localeCode): void;

    public function sortByPrice(string $direction, ChannelInterface $channel): void;

    public function getAvailableFilters(ChannelInterface $channel, string $localeCode, TaxonInterface $taxon): Query;

    public function resetQuery(): void;

    public function getQuery(bool $resetQueue = true): Query;
}
