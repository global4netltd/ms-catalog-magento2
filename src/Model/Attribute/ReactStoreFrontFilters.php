<?php

namespace G4NReact\MsCatalogMagento2\Model\Attribute;

use G4NReact\MsCatalogMagento2\Model\Config\Source\AttributesReactFilter;
use Global4net\Core\Model\Logger;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Eav\Model\AttributeRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\InputException;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class ReactStoreFrontFilters
 * @package G4NReact\MsCatalogMagento2\Model\Attribute
 */
class ReactStoreFrontFilters
{
    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**'
     * @var Logger
     */
    protected $logger;

    /**
     * ReactStoreFrontFilters constructor.
     *
     * @param AttributeRepository $attributeRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        AttributeRepository $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        Logger $logger
    )
    {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * @return AttributeInterface[]
     * @throws InputException
     */
    public function getCategoryAttributes()
    {
        $criteria = $this->searchCriteriaBuilder->create();
        return $this->attributeRepository->getList(Category::ENTITY, $criteria)->getItems();
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getReactStoreFrontFiltersByCategoryId(int $id, $jsonFormat = false)
    {
        try {
            /** @var Category $category */
            $category = $this->categoryRepository->get($id);
            $filters = $category->getData('react_storefront_filters');
        } catch (NoSuchEntityException $exception) {
            $this->logger->log(
                'G4NReact\MsCatalogMagento2',
                [
                    'exception' => $exception->getMessage()
                ]
            );
        }

        if ($jsonFormat) {
            return $filters;
        }

        return $this->serializer->unserialize($filters);
    }

    /**
     * @param Category $category
     * @param array $data
     *
     * @return Category
     */
    public function saveReactStoreFrontFiltersInCategory(Category $category, array $data)
    {
        $data = $this->prepareStorefrontFilters($data);

        if ($data) {
            $category
                ->setReactStorefrontFilters($this->serializer->serialize($data));
        }

        return $category;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function prepareStorefrontFilters(array $data)
    {
        $facets = [];
        $stats = [];
        foreach ($data as $name => $field) {
            foreach ($field as $subfield) {
                switch ($subfield) {
                    case AttributesReactFilter::STATS:
                        $stats [] = $name;
                        break;
                    case AttributesReactFilter::FACETS:
                        $facets [] = $name;
                }
            }
        }
        return [AttributesReactFilter::FACETS => $facets, AttributesReactFilter::STATS => $stats];
    }
}