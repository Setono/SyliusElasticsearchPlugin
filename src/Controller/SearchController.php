<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Controller;

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\Nested;
use Elastica\Query\QueryString;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Adapter\AdapterInterface;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly TaxonRepository $taxonRepository,
        private readonly ProductOptionRepositoryInterface $productOptionRepository,
        private readonly EntityRepository $productAttributeRepository,
        private readonly PaginatedFinderInterface $productFinder,
        private readonly PaginatedFinderInterface $taxonFinder,
        private readonly LocaleContextInterface $localeContext,
        private readonly ChannelContextInterface $channelContext,
        private readonly ElasticSearchRepository $elasticSearchTaxonRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $pagination,
    ) {
    }

    /**
     * Get rendered result for the search box
     */
    public function searchAjaxAction(Request $request, string $queryString): Response
    {
        $products = $taxons = [];
        if ('' !== $queryString) {
            $productLimit = $request->query->getInt('plimit', 10);
            $taxonLimit = $request->query->getInt('tlimit', 5);

            $translationsNested = new Nested();
            $translationsNested->setPath('translations');
            $translationOr = new BoolQuery();

            $queryStringNameObject = new QueryString($queryString . '~');
            $queryStringNameObject->setFields(['translations.name']);
            $queryStringNameObject->setParam('fuzziness', '6');
            $queryStringNameObject->setBoost(2);
            $translationOr->addShould($queryStringNameObject);

            $queryStringDescriptionObject = new QueryString($queryString . '~');
            $queryStringDescriptionObject->setFields(['translations.description']);
            $queryStringDescriptionObject->setParam('fuzziness', '2');
            $queryStringDescriptionObject->setBoost(0.1);
            $translationOr->addShould($queryStringDescriptionObject);

            $localeMatch = new MatchQuery('translations.locale', $this->localeContext->getLocaleCode());
            $translationBool = new BoolQuery();
            $translationBool->addMust($localeMatch);
            $translationBool->addMust($translationOr);
            $translationsNested->setQuery($translationBool);

            $queryObject = new Query($translationsNested);
            $queryObject->setSort(
                [
                    '_score' => [
                        'order' => 'desc',
                    ],
                ],
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
            throw new NotFoundHttpException(sprintf('The taxon with slug "%s" for locale "%s" does not exist', $slug, $this->localeContext->getLocaleCode()));
        }

        if ($request->isXmlHttpRequest()) {
            $response = $this->render('@SetonoSyliusElasticsearchPlugin/results.html.twig', [
                'results' => $this->getResults($request, $taxon),
            ]);

            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->addCacheControlDirective('max-age', false);
            $response->headers->addCacheControlDirective('must-revalidate');
            $response->headers->addCacheControlDirective('no-store');

            return $response;
        }

        $filtersPaginator = $this->productFinder->findPaginated($this->elasticSearchTaxonRepository->getAvailableFilters($this->channelContext->getChannel(), $this->localeContext->getLocaleCode(), $taxon));
        $filtersPaginator->setMaxPerPage($this->pagination);

        /** @var FantaPaginatorAdapter|AdapterInterface $adapter */
        $adapter = $filtersPaginator->getAdapter();
        Assert::isInstanceOf($adapter, FantaPaginatorAdapter::class);

        $filters = $adapter->getAggregations();

        $results = $this->getResults($request, $taxon);
        $resultsUrl = $request->getPathInfo();

        // Make product option name index
        $productOptionNameIndex = [];
        /** @var ProductOptionInterface $productOption */
        foreach ($this->productOptionRepository->findAll() as $productOption) {
            $productOptionNameIndex[(string) $productOption->getCode()] = $productOption->getTranslation()->getName();
        }

        // Make product attribute name index
        $productAttributeNameIndex = [];
        /** @var ProductAttributeInterface $productAttribute */
        foreach ($this->productAttributeRepository->findAll() as $productAttribute) {
            $productAttributeNameIndex[(string) $productAttribute->getCode()] = $productAttribute->getName();
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
            ->whereEnabled()
            ->whereChannel($this->channelContext->getChannel())
            ->whereTaxon($taxon)
            ->whereStock()
        ;

        /** @var list<string> $brands */
        $brands = $request->query->all('brands');
        if ([] !== $brands) {
            $this->elasticSearchTaxonRepository->whereBrands($brands);
        }

        $options = $request->query->all('options');
        if ([] !== $options) {
            $this->elasticSearchTaxonRepository->whereOptions($options);
        }

        $attributes = $request->query->all('attributes');
        if ([] !== $attributes) {
            $this->elasticSearchTaxonRepository->whereAttributes($attributes, $this->localeContext->getLocaleCode());
        }

        $priceFrom = $request->query->getInt('price_from');
        $priceTo = $request->query->getInt('price_to');
        if ($priceFrom > 0 && $priceTo > 0 && $priceTo > $priceFrom) {
            $this->elasticSearchTaxonRepository->whereChannelPrice($priceFrom, $priceTo, $this->channelContext->getChannel());
        }

        $sortField = (string) $request->query->get('sort_field');
        $sortDirection = $request->query->getAlnum('sort_direction');

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

                break;
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
        $queryStringObject->setParam('fuzziness', 'auto');

        $translationsNested->setQuery($queryStringObject);

        $queryObject = new Query($translationsNested);
        $queryObject->setSort(
            [
                '_score' => [
                    'order' => 'desc',
                ],
            ],
        );
        $paginator = $this->productFinder->findPaginated($queryObject);
        $paginator->setMaxPerPage($request->query->getInt('limit', $this->pagination));
        $paginator->setCurrentPage($request->query->getInt('page', 1));

        try {
            if ($paginator->getNbResults() <= 0) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $paginator;
    }

    private function paginateProducts(Request $request, Query $query): Pagerfanta
    {
        $paginator = $this->productFinder->findPaginated($query);
        $paginator->setMaxPerPage($request->query->getInt('limit', $this->pagination));
        $paginator->setCurrentPage($request->query->getInt('page', 1));

        return $paginator;
    }
}
