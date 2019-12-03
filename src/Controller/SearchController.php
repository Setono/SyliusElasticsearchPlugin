<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Controller;

use Elastica\Query;
use Elastica\Query\Nested;
use Elastica\Query\QueryString;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Setono\SyliusElasticsearchPlugin\Repository\ElasticSearchRepository;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Grid\Provider\ArrayGridProvider;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductOption;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    /** @var string */
    private $localeContext;

    /** @var string */
    private $channelContext;

    /** @var ElasticSearchRepository */
    private $elasticSearchTaxonRepository;

    public function __construct(TaxonRepository $taxonRepository,
                                ProductOptionRepositoryInterface $productOptionRepository,
                                EntityRepository $productAttributeRepository,
                                PaginatedFinderInterface $productFinder,
                                PaginatedFinderInterface $taxonFinder,
                                LocaleContextInterface $localeContext,
                                ChannelContextInterface $channelContext,
                                ElasticSearchRepository $elasticSearchTaxonRepository
    ) {
        $this->taxonRepository = $taxonRepository;
        $this->productOptionRepository = $productOptionRepository;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->productFinder = $productFinder;
        $this->taxonFinder = $taxonFinder;
        $this->localeContext = $localeContext;
        $this->channelContext = $channelContext;
        $this->elasticSearchTaxonRepository = $elasticSearchTaxonRepository;
    }

    /**
     * Get rendered result for the search box
     */
    public function searchAjaxAction(Request $request, string $queryString): Response
    {
        $products = $taxons = [];
        if (!empty($queryString)) {
            $productLimit = $request->get('plimit', 10);
            $translationsNested = new Nested();
            $translationsNested->setPath('translations');
            $translationsNested->setQuery(new QueryString('*' . $queryString . '*'));
            $products = $this->productFinder->find(new Query($translationsNested), $productLimit);

            $taxonLimit = $request->get('tlimit', 5);
            $taxons = $this->taxonFinder->find('*' . $queryString . '*', $taxonLimit);
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
        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'query' => $queryString,
            'paginator' => $this->search($request, $queryString),
        ]);
    }

    public function searchTaxonAction(Request $request, string $slug): Response
    {
        /** @var Taxon $taxon */
        $taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());

        $filtersPaginator = $this->productFinder->findPaginated($this->elasticSearchTaxonRepository->getAvailableFilters($this->channelContext->getChannel(), $this->localeContext->getLocaleCode(), $taxon));
        $filtersPaginator->setMaxPerPage(20);
        $filters = $this->getFilterTranslations($filtersPaginator->getAdapter()->getAggregations());

        $results = $this->getResults($request, $taxon);
        $resultsUrl = $request->getPathInfo() . '/results';

        // Make product option name index
        $productOptionNameIndex = [];
        foreach ($this->productOptionRepository->findAll() as $productOption) {
            /**
             * @var ProductOption
             */
            $productOptionNameIndex[$productOption->getCode()] = $productOption->getTranslation()->getName();
        }

        // Make product attribute name index
        $productAttributeNameIndex = [];
        foreach ($this->productAttributeRepository->findAll() as $productAttribute) {
            /**
             * @var ProductAttribute
             */
            $productAttributeNameIndex[$productAttribute->getCode()] = $productAttribute->getName();
        }

        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'isCategory' => true,
            'results' => $results,
            'resultsUrl' => $resultsUrl,
            'filters' => $filters,
            'taxon' => $taxon,
            'productOptionNameIndex' => $productOptionNameIndex,
            'productAttributeNameIndex' => $productAttributeNameIndex,
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

    public function getResults(Request $request, TaxonInterface $taxon)
    {
        $this->elasticSearchTaxonRepository
            ->whereChannel($this->channelContext->getChannel())
            ->whereTaxon($taxon);

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

        $priceFrom = $request->get('price_from');
        $priceTo = $request->get('price_to');
        if ($priceFrom && $priceTo) {
            $this->elasticSearchTaxonRepository->whereChannelPrice((int) $priceFrom, (int) $priceTo, $this->channelContext->getChannel());
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
        }

        return $this->paginateProducts($request, $this->elasticSearchTaxonRepository->getQuery());
    }

    /**
     * Perform a product search using the index defined for the active locale.
     */
    private function search(Request $request, string $queryString = '', string $taxonCode = ''): Pagerfanta
    {
        /** @var ArrayGridProvider $gridProvider */
        $gridProvider = $this->get('sylius.grid.provider');
        $grid = $gridProvider->get('sylius_shop_product');

        $paginator = $this->productFinder->findPaginated('*' . $queryString . '*');
        $paginator->setMaxPerPage($request->get('limit', $grid->getLimits()[0]));
        $paginator->setCurrentPage($request->get('page', 1));

        return $paginator;
    }

    private function paginateProducts(Request $request, $query)
    {
        $gridProvider = $this->get('sylius.grid.provider');
        $grid = $gridProvider->get('sylius_shop_product');

        $paginator = $this->productFinder->findPaginated($query);
        $paginator->setMaxPerPage($request->get('limit', $grid->getLimits()[0]));
        $paginator->setCurrentPage($request->get('page', 1));

        return $paginator;
    }

    private function getFilterTranslations(array $filters)
    {
        return $filters;
    }
}
