<?php

namespace G4NReact\MsCatalogMagento2\Model\Puller;

use G4NReact\MsCatalog\Document;
use G4NReact\MsCatalog\QueryInterface;
use G4NReact\MsCatalog\ResponseInterface;
use G4NReact\MsCatalogMagento2\Helper\Config as ConfigHelper;
use G4NReact\MsCatalogMagento2\Helper\SearchTerms\SearchTermsField;
use G4NReact\MsCatalogMagento2\Model\AbstractPuller;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Search\Model\ResourceModel\Query\Collection;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory;
use Magento\Search\Model\ResourceModel\SynonymGroup\CollectionFactory as SynonymGroupCollFactory;

class SearchTermsPuller extends AbstractPuller
{
    /**
     * @var string Type of object
     */
    const OBJECT_TYPE = SearchTermsField::OBJECT_TYPE;

    /**
     * @var CollectionFactory
     */
    protected $searchQueryCollFactory;

    /**
     * @var Manager
     */
    protected $eventManager;

    /**
     * @var SearchTermsField
     */
    protected $searchTermsField;

    /**
     * @var SynonymGroupCollFactory
     */
    protected $synonymGroupCollFactory;

    /**
     * @var
     */
    protected $synonymGroupCollection;

    /**
     * SearchTermsPuller constructor.
     *
     * @param ConfigHelper $magento2ConfigHelper
     * @param CollectionFactory $searchQueryCollFactory
     * @param Manager $eventManager
     * @param SearchTermsField $searchTermsField
     *
     * @throws NoSuchEntityException
     */
    public function __construct(
        ConfigHelper $magento2ConfigHelper,
        CollectionFactory $searchQueryCollFactory,
        Manager $eventManager,
        SearchTermsField $searchTermsField,
        SynonymGroupCollFactory $synonymGroupCollFactory
    )
    {
        $this->searchQueryCollFactory = $searchQueryCollFactory;
        $this->eventManager = $eventManager;
        $this->searchTermsField = $searchTermsField;
        $this->synonymGroupCollFactory = $synonymGroupCollFactory;
        parent::__construct($magento2ConfigHelper);
    }

    /**
     * @return Collection|mixed
     * @throws NoSuchEntityException
     */
    public function getCollection()
    {
        $collection = $this->searchQueryCollFactory->create();

        if ($this->getIds()) {
            $collection->addFieldToFilter('main_table.query_id', ['in' => $this->getIds()]);
        }

        $collection
            ->addStoreFilter($this->magento2ConfigHelper->getStore()->getId())
            ->setPageSize($this->getPageSize())
            ->setCurPage($this->getCurPage());

        $this->addSynonymsToSearchTermsCollection($collection);

        $this->eventManager->dispatch('ms_catalog_get_search_terms_collection', ['collection' => $collection]);

        return $collection;
    }

    /**
     * @param Collection $collection
     *
     * @throws NoSuchEntityException
     */
    protected function addSynonymsToSearchTermsCollection(Collection &$collection)
    {
        $synonymGroupCollection = $this->getSynonymGroupCollection();
        foreach ($synonymGroupCollection as $synonyms) {
            foreach ($collection as $queryText) {
                $synonymsText = explode(',', $synonyms->getSynonyms());
                $synonymKey = array_search(trim($queryText['query_text']), $synonymsText);
                if ($synonymKey !== false) {
                    unset($synonymsText[$synonymKey]);
                    $queryText->setData('synonyms', $synonymsText);
                }
            }
        }
    }

    /**
     * @return \Magento\Search\Model\ResourceModel\SynonymGroup\Collection
     * @throws NoSuchEntityException
     */
    protected function getSynonymGroupCollection()
    {
        if (!$this->synonymGroupCollection) {
            $this->synonymGroupCollection = $this->synonymGroupCollFactory->create()
                ->addFieldToFilter('store_id', ['in' => [0, $this->magento2ConfigHelper->getStore()->getId()]]);
        }

        return $this->synonymGroupCollection;
    }

    /**
     * @return Document
     * @throws NoSuchEntityException
     */
    public function current(): Document
    {
        $searchTerm = $this->pageArray[$this->position];
        $storeId = $this->magento2ConfigHelper->getStore()->getId();

        $document = new Document();

        $eventData = [
            'search_term' => $searchTerm,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_search_term_before', ['eventData' => $eventData]);

        $document->setUniqueId($searchTerm->getId() . '_' . SearchTermsField::OBJECT_TYPE . '_' . $storeId);
        $document->setObjectId($searchTerm->getId());
        $document->setObjectType(SearchTermsField::OBJECT_TYPE);

        foreach ($searchTerm->getData() as $field => $value) {
            $document->createField(
                $field,
                $searchTerm->getData($field),
                $this->searchTermsField->getFieldTypeByColumnName($field),
                SearchTermsField::getIsIndexable($field),
                SearchTermsField::getIsMultiValued($field, $value)
            );
        }

        $eventData = [
            'search_term' => $searchTerm,
            'document' => $document,
        ];
        $this->eventManager->dispatch('prepare_document_from_search_term_after', $eventData);

        return $document;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return SearchTermsField::OBJECT_TYPE;
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
