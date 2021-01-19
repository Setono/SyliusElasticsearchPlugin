<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Controller;

use Elastica\Exception\ResponseException;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Query\Nested;
use Elastica\Query\QueryString;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusElasticsearchPlugin\Event\ProductIndexEvent;
use Setono\SyliusElasticsearchPlugin\Repository\ElasticSearchRepository;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SearchController extends Controller
{
    /** @var TaxonRepository */
    private $taxonRepository;

    /** @var ProductOptionRepositoryInterface */
    private $productOptionRepository;

    /** @var EntityRepository */
    private $productAttributeRepository;

    /** @var PaginatedFinderInterface */
    private $productFinder;

    /** @var PaginatedFinderInterface */
    private $taxonFinder;

    /** @var LocaleContextInterface */
    private $localeContext;

    /** @var ChannelContextInterface */
    private $channelContext;

    /** @var ElasticSearchRepository */
    private $elasticSearchTaxonRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var int */
    private $pagination;

    public function __construct(
        TaxonRepository $taxonRepository,
        ProductOptionRepositoryInterface $productOptionRepository,
        EntityRepository $productAttributeRepository,
        PaginatedFinderInterface $productFinder,
        PaginatedFinderInterface $taxonFinder,
        LocaleContextInterface $localeContext,
        ChannelContextInterface $channelContext,
        ElasticSearchRepository $elasticSearchTaxonRepository,
        EventDispatcherInterface $eventDispatcher,
        int $pagination
    ) {
        $this->taxonRepository = $taxonRepository;
        $this->productOptionRepository = $productOptionRepository;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->productFinder = $productFinder;
        $this->taxonFinder = $taxonFinder;
        $this->localeContext = $localeContext;
        $this->channelContext = $channelContext;
        $this->elasticSearchTaxonRepository = $elasticSearchTaxonRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->pagination = $pagination;
    }

    /**
     * Get rendered result for the search box
     */
    public function searchAjaxAction(Request $request, string $queryString): Response
    {
        $products = $taxons = [];
        if ('' !== $queryString) {
            $productLimit = $request->get('plimit', 10);
            $taxonLimit = $request->get('tlimit', 5);

            $translationsNested = new Nested();
            $translationsNested->setPath('translations');
            $queryStringObject = new QueryString($queryString . '~');
            $queryStringObject->setParam('fuzziness', '10');

            $localeMatch = new Match('translations.locale', $this->localeContext->getLocaleCode());
            $translationBool = new BoolQuery();
            $translationBool->addMust($localeMatch);
            $translationBool->addMust($queryStringObject);
            $translationsNested->setQuery($translationBool);

            $queryObject = new Query($translationsNested);
            $queryObject->setSort(
                [
                    '_score' => [
                        'order' => 'desc',
                    ],
                ]
            );

            $products = $this->productFinder->find($queryObject, $productLimit);
            $taxons = $this->taxonFinder->find($queryObject, $taxonLimit);
        }

        return $this->render('@SyliusShop/Homepage/_search.html.twig', [
            'query' => $queryString,
            'products' => $products,
            'taxons' => $taxons,
        ]);
    }

    /**
     * Search page
     */
    public function searchListAction(Request $request, string $queryString): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('@SetonoSyliusElasticsearchPlugin/results.html.twig', [
                'results' => $this->search($request, $queryString),
            ]);
        }

        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'query' => $queryString,
            'resultsUrl' => $request->getPathInfo() . '/results',
            'results' => $this->search($request, $queryString),
        ]);
    }

    /**
     * Search results page
     */
    public function searchListResultsAction(Request $request, string $queryString): Response
    {
        return $this->render('@SetonoSyliusElasticsearchPlugin/results.html.twig', [
            'results' => $this->search($request, $queryString),
        ]);
    }

    public function searchTaxonAction(Request $request, string $slug): Response
    {
        /** @var TaxonInterface|null $taxon */
        $taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());
        if (null === $taxon) {
            throw new NotFoundHttpException(\Safe\sprintf('The taxon with slug "%s" for locale "%s" does not exist', $slug, $this->localeContext->getLocaleCode()));
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@SetonoSyliusElasticsearchPlugin/results.html.twig', [
                'results' => $this->getResults($request, $taxon),
            ]);
        }

        $filtersPaginator = $this->productFinder->findPaginated($this->elasticSearchTaxonRepository->getAvailableFilters($this->channelContext->getChannel(), $this->localeContext->getLocaleCode(), $taxon));
        $filtersPaginator->setMaxPerPage($this->pagination);
        $filters = $filtersPaginator->getAdapter()->getAggregations();

        $results = $this->getResults($request, $taxon);
        $resultsUrl = $request->getPathInfo();

        // Make product option name index
        $productOptionNameIndex = [];
        /** @var ProductOptionInterface $productOption */
        foreach ($this->productOptionRepository->findAll() as $productOption) {
            $productOptionNameIndex[$productOption->getCode()] = $productOption->getTranslation()->getName();
        }

        // Make product attribute name index
        $productAttributeNameIndex = [];
        /** @var ProductAttributeInterface $productAttribute */
        foreach ($this->productAttributeRepository->findAll() as $productAttribute) {
            $productAttributeNameIndex[$productAttribute->getCode()] = $productAttribute->getName();
        }

        $this->eventDispatcher->dispatch(new ProductIndexEvent($results, $taxon));

        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'isCategory' => true,
            'results' => $results,
            'resultsUrl' => $resultsUrl,
            'filters' => $filters,
            'taxon' => $taxon,
            'productOptionNameIndex' => $productOptionNameIndex,
            'productAttributeNameIndex' => $productAttributeNameIndex,
            'request' => $request,
        ]);
    }

    public function searchTaxonResultsAction(Request $request, string $slug): Response
    {
        /** @var Taxon $taxon */
        $taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());

        return $this->render('@SetonoSyliusElasticsearchPlugin/results.html.twig', [
            'results' => $this->getResults($request, $taxon),
        ]);
    }

    public function getResults(Request $request, TaxonInterface $taxon): Pagerfanta
    {
        $this->elasticSearchTaxonRepository
            ->whereChannel($this->channelContext->getChannel())
            ->whereTaxon($taxon)
            ->whereStock();

        $brands = $request->get('brands');
        if (is_array($brands)) {
            $this->elasticSearchTaxonRepository->whereBrands($brands);
        }

        $options = $request->get('options');
        if (is_array($options)) {
            $this->elasticSearchTaxonRepository->whereOptions($options);
        }

        $attributes = $request->get('attributes');
        if (is_array($attributes)) {
            $this->elasticSearchTaxonRepository->whereAttributes($attributes, $this->localeContext->getLocaleCode());
        }

        $priceFrom = (int) $request->get('price_from', 0);
        $priceTo = (int) $request->get('price_to', 0);
        if ($priceFrom > 0 && $priceTo > 0 && $priceTo > $priceFrom) {
            $this->elasticSearchTaxonRepository->whereChannelPrice($priceFrom, $priceTo, $this->channelContext->getChannel());
        }

        $sortField = $request->get('sort_field');
        $sortDirection = $request->get('sort_direction');

        switch ($sortField) {
            case 'createdAt':
                $this->elasticSearchTaxonRepository->sortByCreated($sortDirection);

                break;
            case 'name':
                $this->elasticSearchTaxonRepository->sortByProductName($sortDirection, $this->localeContext->getLocaleCode());

                break;
            case 'price':
                $this->elasticSearchTaxonRepository->sortByPrice($sortDirection, $this->channelContext->getChannel());

                break;
            default:
                $this->elasticSearchTaxonRepository->sortByPosition();
        }

        return $this->paginateProducts($request, $this->elasticSearchTaxonRepository->getQuery());
    }

    /**
     * Perform a product search using the index defined for the active locale.
     */
    private function search(Request $request, string $queryString = ''): ?Pagerfanta
    {
        $translationsNested = new Nested();
        $translationsNested->setPath('translations');
        $queryStringObject = new QueryString($queryString . '~');
        $queryStringObject->setParam('fuzziness', '10');

        $translationsNested->setQuery($queryStringObject);

        $queryObject = new Query($translationsNested);
        $queryObject->setSort(
            [
                '_score' => [
                    'order' => 'desc',
                ],
            ]
        );
        $paginator = $this->productFinder->findPaginated($queryObject);
        $paginator->setMaxPerPage($request->get('limit', $this->pagination));
        $paginator->setCurrentPage($request->get('page', 1));

        try {
            if (null === $paginator->getNbResults()) {
                return null;
            }
        } catch (ResponseException $exception) {
            return null;
        }

        return $paginator;
    }

    private function paginateProducts(Request $request, Query $query): Pagerfanta
    {
        $paginator = $this->productFinder->findPaginated($query);
        $paginator->setMaxPerPage($request->get('limit', $this->pagination));
        $paginator->setCurrentPage($request->get('page', 1));

        return $paginator;
    }
}
