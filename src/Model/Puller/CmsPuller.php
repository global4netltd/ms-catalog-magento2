<?php

namespace G4NReact\MsCatalogMagento2\Model\Puller;

use G4NReact\MsCatalog\Document;
use G4NReact\MsCatalogMagento2\Model\AbstractPuller;
use G4NReact\MsCatalogMagento2\Helper\MsCatalog as MsCatalogHelper;
use Magento\Cms\Model\ResourceModel\Page\Collection as CmsPageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Magento\Eav\Model\ResourceModel\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;

/**
 * Class CmsPuller
 * @package G4NReact\MsCatalogMagento2\Model\Puller
 */
class CmsPuller extends AbstractPuller
{
    /**
     * @var array
     */
    public static $fieldTypeMap = [
        'page_id' => 'int',
        'title' => 'string',
        'page_layout' => 'string',
        'meta_keywords' => 'string',
        'meta_description' => 'string',
        'identifier' => 'string',
        'content_heading' => 'string',
        'content' => 'string',
        'creation_time' => 'datetime',
        'update_time' => 'datetime',
        'is_active' => 'bool',
        'sort_order' => 'int',
        'store_id' => 'int',
    ];

    /**
     * @var CmsPageCollectionFactory
     */
    protected $cmsPageCollectionFactory;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * CmsPuller constructor
     *
     * @param CmsPageCollectionFactory $cmsPageCollectionFactory
     * @param EavConfig $eavConfig
     * @param Attribute $eavAttribute
     * @param MsCatalogHelper $msCatalogHelper
     */
    public function __construct(
        CmsPageCollectionFactory $cmsPageCollectionFactory,
        EavConfig $eavConfig,
        Attribute $eavAttribute,
        MsCatalogHelper $msCatalogHelper
    ) {
        $this->cmsPageCollectionFactory = $cmsPageCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->eavAttribute = $eavAttribute;

        parent::__construct($msCatalogHelper);
    }

    /**
     * @return CmsPageCollection
     */
    public function getCollection(): CmsPageCollection
    {
        $cmsPageCollection = $this->cmsPageCollectionFactory->create();

        if ($this->ids !== null) {
            $cmsPageCollection->addAttributeToFilter('entity_id', array('in' => $this->ids));
        }

        $cmsPageCollection
            ->addFieldToSelect('page_id')
            ->addFieldToSelect('title')
            ->addFieldToSelect('page_layout')
            ->addFieldToSelect('meta_keywords')
            ->addFieldToSelect('meta_description')
            ->addFieldToSelect('identifier')
            ->addFieldToSelect('content_heading')
            ->addFieldToSelect('content')
            ->addFieldToSelect('creation_time')
            ->addFieldToSelect('update_time')
            ->addFieldToSelect('is_active')
            ->addFieldToSelect('sort_order')
            ->setPageSize($this->pageSize)
            ->setCurPage($this->curPage);

        return $cmsPageCollection;
    }

    /**
     * @return Document
     */
    public function current(): Document
    {
        $page = $this->pageArray[$this->position];

        $document = new Document();

        $document->setUniqueId($page->getId() . '_' . 'cms' . '_' . $page->getStoreId()[0]);
        $document->setObjectId($page->getId());
        $document->setObjectType('cms'); // @ToDo: move it to const

        foreach ($page->getData() as $field => $value) {
            $document->setField(
                $field,
                $page->getData($field),
                self::$fieldTypeMap[$field] ?? 'string',
                false
            );
        }

        return $document;
    }
}
