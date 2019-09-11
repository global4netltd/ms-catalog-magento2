<?php

namespace G4NReact\MsCatalogMagento2\Model\Puller;

use G4NReact\MsCatalog\Document;
use G4NReact\MsCatalog\QueryInterface;
use G4NReact\MsCatalog\ResponseInterface;
use G4NReact\MsCatalogMagento2\Helper\Cms\CmsBlockField;
use G4NReact\MsCatalogMagento2\Helper\Config as ConfigHelper;
use G4NReact\MsCatalogMagento2\Model\AbstractPuller;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Widget\Model\Template\FilterEmulate;

/**
 * Class CmsBlockPuller
 * @package G4NReact\MsCatalogMagento2\Model\Puller
 */
class CmsBlockPuller extends AbstractPuller
{
    /**
     * @var string Type of object
     */
    const OBJECT_TYPE = 'cms_block';

    /**
     * @var string
     */
    public $type;

    /**
     * @var Manager
     */
    protected $eventManager;

    /**
     * @var CollectionFactory
     */
    protected $cmsBlockCollFactory;

    /**
     * @var CmsBlockField
     */
    protected $cmsBlockField;

    /**
     * @var FilterEmulate
     */
    protected $widgetFilter;

    /**
     * CmsBlockPuller constructor.
     *
     * @param ConfigHelper $magento2ConfigHelper
     * @param Manager $eventManager
     * @param CollectionFactory $cmsBlockCollFactory
     * @param CmsBlockField $cmsBlockField
     *
     * @throws NoSuchEntityException
     */
    public function __construct(
        ConfigHelper $magento2ConfigHelper,
        Manager $eventManager,
        CollectionFactory $cmsBlockCollFactory,
        CmsBlockField $cmsBlockField,
        FilterEmulate $widgetFilter
    ) {
        $this->type = self::OBJECT_TYPE;
        $this->eventManager = $eventManager;
        $this->cmsBlockCollFactory = $cmsBlockCollFactory;
        $this->cmsBlockField = $cmsBlockField;
        $this->widgetFilter = $widgetFilter;
        $this->setType(self::OBJECT_TYPE);

        parent::__construct($magento2ConfigHelper);
    }

    /**
     * @return \Magento\Cms\Model\ResourceModel\Block\Collection|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCollection()
    {
        $collection = $this->cmsBlockCollFactory->create();

        if ($this->ids !== null) {
            $collection->addFieldToFilter('block_id', array('in' => $this->ids));
        }

        $collection->addStoreFilter($this->magento2ConfigHelper->getStore()->getId())
            ->setPageSize($this->getPageSize())
            ->setCurPage($this->getCurPage());

        $this->eventManager->dispatch('ms_catalog_get_cms_block_collection', ['collection' => $collection]);

        return $collection;
    }

    /**
     * @return Document
     * @throws NoSuchEntityException
     */
    public function current(): Document
    {
        $cmsBlock = $this->pageArray[$this->position];
        $storeId = $this->magento2ConfigHelper->getStore()->getId();

        $document = new Document();

        $eventData = [
            'cms_block' => $cmsBlock,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_cms_block_before', ['eventData' => $eventData]);

        $document->setUniqueId($cmsBlock->getId() . '_' . self::OBJECT_TYPE . '_' . $storeId);
        $document->setObjectId($cmsBlock->getId());
        $document->setObjectType(self::OBJECT_TYPE);

        foreach ($cmsBlock->getData() as $field => $value) {
            $fieldValue = ($field == BlockInterface::CONTENT)
                ? $this->widgetFilter->filter($cmsBlock->getData($field))
                : $cmsBlock->getData($field);
            
            $document->createField(
                $field,
                $fieldValue,
                $this->cmsBlockField->getFieldTypeByColumnName($field),
                CmsBlockField::getIsIndexable($field),
                CmsBlockField::getIsMultiValued($field, $value)
            );
        }

        if ($storeIdField = $document->getField('store_id')) {
            if (is_array($storeIdField->getValue()) && in_array(0, $storeIdField->getValue())) {
                $storeIdField->setValue($storeId);
                $storeIdField->setType(Document\Field::FIELD_TYPE_INT);
                $storeIdField->setMultiValued(false);
            }
        }

        $eventData = [
            'cms_block' => $cmsBlock,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_cms_block_after', $eventData);

        return $document;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function pull(QueryInterface $query = null): ResponseInterface
    {
        // TODO: Implement pull() method.
    }
}
