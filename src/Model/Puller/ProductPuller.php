<?php

namespace G4NReact\MsCatalogMagento2\Model\Puller;

use G4NReact\MsCatalog\Document;
use G4NReact\MsCatalog\QueryInterface;
use G4NReact\MsCatalog\ResponseInterface;
use G4NReact\MsCatalogMagento2\Helper\Config as ConfigHelper;
use G4NReact\MsCatalogMagento2\Helper\BaseQuery as QueryHelper;
use G4NReact\MsCatalogMagento2\Model\AbstractPuller;
use G4NReact\MsCatalogMagento2\Model\Attribute\SearchTerms;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

/**
 * Class ProductPuller
 * @package G4NReact\MsCatalogMagento2\Model\Puller
 */
class ProductPuller extends AbstractPuller
{
    /**
     * @var string Type of object
     */
    const OBJECT_TYPE = 'product';

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;
    /**
     * @var SearchTerms
     */
    protected $searchTerms;
    /**
     * @var QueryHelper
     */
    protected $queryHelper;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * ProductPuller constructor
     *
     * @param ProductCollectionFactory $productCollectionFactory
     * @param EavConfig $eavConfig
     * @param Attribute $eavAttribute
     * @param JsonSerializer $jsonSerializer
     * @param ConfigHelper $magento2ConfigHelper
     * @param SearchTerms $searchTerms
     * @param QueryHelper $queryHelper
     * @param EventManager $eventManager
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        EavConfig $eavConfig,
        Attribute $eavAttribute,
        JsonSerializer $jsonSerializer,
        ConfigHelper $magento2ConfigHelper,
        SearchTerms $searchTerms,
        QueryHelper $queryHelper,
        EventManager $eventManager
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->eavAttribute = $eavAttribute;
        $this->jsonSerializer = $jsonSerializer;
        $this->searchTerms = $searchTerms;
        $this->queryHelper = $queryHelper;
        $this->eventManager = $eventManager;

        parent::__construct($magento2ConfigHelper);
    }

    /**
     * @return ProductCollection
     * @throws LocalizedException
     */
    public function getCollection(): ProductCollection
    {
        /** @var ProductCollection $productCollection */
        $productCollection = $this->productCollectionFactory->create();

        if ($this->ids !== null) {
            $productCollection->addAttributeToFilter('entity_id', ['in' => $this->ids]);
        }

        $productCollection->addAttributeToSelect('*')
            ->addStoreFilter()
            ->setPageSize($this->pageSize)
            ->setCurPage($this->curPage)
            ->addFinalPrice()
            ->addUrlRewrite()
            ->addMediaGalleryData();

        $this->eventManager->dispatch('ms_catalog_get_product_collection', ['collection' => $productCollection]);

        return $productCollection;
    }

    /**
     * @return Document
     * @throws LocalizedException
     */
    public function current(): Document
    {
        /** @var Product $product */
        $product = $this->pageArray[$this->position];
        $document = new Document();

        $eventData = [
            'product' => $product,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_product_before', ['eventData' => $eventData]);

        $document->setUniqueId($product->getId() . '_' . self::OBJECT_TYPE . '_' . $product->getStoreId());
        $document->setObjectId($product->getId());
        $document->setObjectType(self::OBJECT_TYPE);

        foreach ($product->getData() as $field => $value) {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $field);

            $searchTermField = $this->searchTerms->prepareSearchTermField($attribute->getAttributeCode());
            if ($searchTermField) {
                if ($document->getField($searchTermField)) {
                    $document->createField(
                        $searchTermField,
                        $document->getField($searchTermField)
                        . ' ' . $product->getData(
                            $attribute->getAttributeCode()
                        ));
                } else {
                    $document->createField(
                        $searchTermField,
                        $product->getData($attribute->getAttributeCode()),
                        'string',
                        true,
                        true
                    );
                }
            }

            $document->setField(
                $this->queryHelper->getFieldByAttribute($attribute, $product->getData($attribute->getAttributeCode()))
            );
        }

        $mediaGalleryJson = $this->getMediaGalleryJson($product->getMediaGalleryImages());
        $document->createField(
            'media_gallery',
            $mediaGalleryJson,
            'string',
            false,
            false
        );

        $document->createField(
            'category_id',
            $product->getCategoryIds(),
            QueryHelper::$mapAttributeCodeToFieldType['category_id']['type'] ?? 'int',
            QueryHelper::$mapAttributeCodeToFieldType['category_id']['indexable'] ?? true,
            QueryHelper::$mapAttributeCodeToFieldType['category_id']['multivalued'] ?? true
        );

        $eventData = [
            'product' => $product,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_product_after', ['eventData' => $eventData]);

        return $document;
    }

    /**
     * @param DataCollection $mediaGalleryImages
     *
     * @return bool|false|string
     */
    protected function getMediaGalleryJson(DataCollection $mediaGalleryImages)
    {
        $gallery = [];

        foreach ($mediaGalleryImages as $image) {
            $gallery[] = ['full' => $image->getUrl()];
        }

        return $this->jsonSerializer->serialize($gallery);
    }

    /**
     * @param QueryInterface|null $query
     *
     * @return ResponseInterface
     */
    public function pull(QueryInterface $query = null): ResponseInterface
    {
        // TODO: Implement pull() method.
    }
}
