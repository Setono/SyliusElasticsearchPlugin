<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Controller;

use Setono\SyliusElasticsearchPlugin\Model\ElasticsearchQueryConfiguration;
use Doctrine\ORM\EntityNotFoundException;
use FOS\ElasticaBundle\Finder\FinderInterface;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\AttributeRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository;
use Sylius\Component\Attribute\Model\Attribute;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Grid\Provider\ArrayGridProvider;
use Sylius\Component\Product\Repository\ProductAttributeValueRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $channel;

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->locale = $this->get('sylius.context.locale')->getLocaleCode();
        $this->channel = $this->get('sylius.context.channel')->getChannel()->getCode();
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
            $productFinder = $this->getProductFinder();
            $products = $productFinder->find('*' . $queryString . '*', $productLimit);

            /** @var FinderInterface $taxonFinder */
            $taxonFinder = $this->getTaxonFinder();
            $taxonLimit = $request->get('tlimit', 5);
            $taxons = $taxonFinder->find('*' . $queryString . '*', $taxonLimit);
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
            'filters' => $this->getAttributeFilterOptions($request),
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
        $taxon = $taxonRepository->findOneBySlug($slug, $this->locale);

        $paginator = $this->search($request, '', $taxon->getCode());

        return $this->render('@SetonoSyliusElasticsearchPlugin/index.html.twig', [
            'isCategory' => true,
            'paginator' => $paginator,
            'filters' => $this->getAttributeFilterOptions($request),
            'taxon' => $taxon
        ]);
    }

    /**
     * Returns localized channel product-finder
     *
     * @return FinderInterface
     */
    private function getProductFinder(): FinderInterface
    {
        $index = $this->getParameter('setono_sylius_elasticsearch.config')['finder_indexes'][strtolower("{$this->channel}_{$this->locale}")]['products'];
        return $this->get("fos_elastica.finder.{$index}.default");
    }

    /**
     * Returns localized channel taxon-finder
     *
     * @return FinderInterface
     */
    private function getTaxonFinder(): FinderInterface
    {
        $index = $this->getParameter('setono_sylius_elasticsearch.config')['finder_indexes'][strtolower("{$this->channel}_{$this->locale}")]['taxons'];
        return $this->get("fos_elastica.finder.{$index}.default");
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
        /** @var ChannelContextInterface $channelContext */
        $channelContext = $this->get('sylius.context.channel');
        $channel = $channelContext->getChannel();

        $config = $this->container->getParameter('setono_sylius_elasticsearch.config');

        /** @var ArrayGridProvider $gridProvider */
        $gridProvider = $this->get('sylius.grid.provider');
        $grid = $gridProvider->get('sylius_shop_product');

        /** @var PaginatedFinderInterface $productFinder */
        $productFinder = $this->getProductFinder();

        // Filter on defined attributes
        $attributeFilters = [];
        foreach ($config['attributes'] as $attributeName) {
            $attributeFilters[$attributeName] = $request->get($attributeName);
        }

        // Build query
        $queryConfig = new ElasticsearchQueryConfiguration($request, $queryString, $taxonCode, $attributeFilters);
        $queryConfig->setChannel($channel->getCode());

        $paginator = $productFinder->findPaginated($queryConfig->getQuery());
        $paginator->setMaxPerPage($request->get('limit', $grid->getLimits()[0]));
        $paginator->setCurrentPage($request->get('page', 1));

        return $paginator;
    }

    /**
     * Get attribute values used for the filter form on the search page.
     *
     * @return array
     */
    private function getAttributeFilterOptions(Request $request): array
    {
        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = $this->get('sylius.repository.product_attribute');
        /** @var ProductAttributeValueRepositoryInterface $attributeValueRepository */
        $attributeValueRepository = $this->get('sylius.repository.product_attribute_value');

        $config = $this->container->getParameter('setono_sylius_elasticsearch.config');

        $return = ['selected' => []];
        foreach ($config['attributes'] as $attributeName) {
//            /** @var Attribute $attribute */
//            $attribute = $attributeRepository->findOneBy(['code' => $attributeName]);

//            if(!$attribute) {
//                throw new EntityNotFoundException("Product attribute \"{$attributeName}\" could not be found");
//            }

//            $return[$attributeName] = $attributeValueRepository->findValuesByAttributeCode($attribute->getCode(), $this->locale);
//            $return['selected'][$attributeName] = $request->get($attributeName);
        }

        return $return;
    }
}
