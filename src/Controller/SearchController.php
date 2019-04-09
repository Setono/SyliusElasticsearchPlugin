<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Controller;

use Doctrine\ORM\EntityNotFoundException;
use Setono\SyliusElasticsearchPlugin\Model\ElasticsearchQueryConfiguration;
use FOS\ElasticaBundle\Finder\FinderInterface;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Setono\SyliusElasticsearchPlugin\Repository\ElasticSearchRepository;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Grid\Provider\ArrayGridProvider;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Setono\SyliusElasticsearchPlugin\Repository\ProductAttributeValueRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    /**
     * @var PaginatedFinderInterface
     */
    private $productFinder;

    /**
     * @var PaginatedFinderInterface
     */
    private $taxonFinder;

    /**
     * @var string
     */
    private $localeContext;

    /**
     * @var string
     */
    private $channelContext;

    /**
     * @var ElasticSearchRepository
     */
    private $elasticSearchTaxonRepository;

    public function __construct(PaginatedFinderInterface $productFinder,
                                PaginatedFinderInterface $taxonFinder,
                                LocaleContextInterface $localeContext,
                                ChannelContextInterface $channelContext,
                                ElasticSearchRepository $elasticSearchTaxonRepository
    )
    {
        $this->productFinder = $productFinder;
        $this->taxonFinder = $taxonFinder;
        $this->localeContext = $localeContext;
        $this->channelContext = $channelContext;
        $this->elasticSearchTaxonRepository = $elasticSearchTaxonRepository;
    }

    /**
     * Get rendered result for the search box
     *
     * @param Request $request
     * @param string  $queryString
     *
     * @return Response
     */
    public function searchAjaxAction(Request $request, string $queryString): Response
    {
        $products = $taxons = [];
        if (!empty($queryString)) {
            $productLimit = $request->get('plimit', 10);
            $products = $this->productFinder->find('*' . $queryString . '*', $productLimit);

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
     *
     * @param Request $request
     * @param string  $queryString
     *
     * @return Response
     */
    public function searchListAction(Request $request, string $queryString): Response
    {
        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'query' => $queryString,
            'paginator' => $this->search($request, $queryString),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $slug
     *
     * @return Response
     */
    public function searchTaxonAction(Request $request, string $slug): Response
    {
        /** @var TaxonRepository $taxonRepository */
        $taxonRepository = $this->get('sylius.repository.taxon');
        /** @var Taxon $taxon */
        $taxon = $taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());

        $this->elasticSearchTaxonRepository
            ->whereChannel($this->channelContext->getChannel())
            ->whereTaxon($taxon);

        $brands = $request->get('brands');
        if(is_array($brands)) {
            $this->elasticSearchTaxonRepository->whereBrands($brands);
        }

        $options = $request->get('options');
        if(is_array($options)) {
            $this->elasticSearchTaxonRepository->whereOptions($options);
        }

        $attributes = $request->get('attributes');
        if(is_array($attributes)) {
            $this->elasticSearchTaxonRepository->whereAttributes($attributes, $this->localeContext->getLocaleCode());
        }

        $priceFrom = $request->get('price_from');
        $priceTo = $request->get('price_to');
        if($priceFrom && $priceTo) {
            $this->elasticSearchTaxonRepository->whereChannelPrice(intval($priceFrom), intval($priceTo), $this->channelContext->getChannel());
        }

        $paginator = $this->paginateProducts($request, $this->elasticSearchTaxonRepository->getQuery());

        $filtersPaginator = $this->productFinder->findPaginated($this->elasticSearchTaxonRepository->getAvailableFilters($this->channelContext->getChannel(), $this->localeContext->getLocaleCode(), $taxon));
        $filtersPaginator->setMaxPerPage(20);
        $filters = $filtersPaginator->getAdapter()->getAggregations();

        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'isCategory' => true,
            'paginator' => $paginator,
            'filters' => $filters,
            'taxon' => $taxon
        ]);
    }

    /**
     * Perform a product search using the index defined for the active locale.
     *
     * @param Request $request
     * @param string  $queryString
     * @param string  $taxonCode
     *
     * @return Pagerfanta
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
}
