<?php

namespace G4NReact\MsCatalogMagento2\Console\Command;

use G4NReact\MsCatalogMagento2\Helper\MsCatalog as MsCatalogHelper;
use G4NReact\MsCatalogMagento2\Model\Puller\CategoryPuller;
use Magento\Framework\App\State as AppState;
use Magento\Store\Model\App\Emulation;

/**
 * Class ReindexCategory
 * @package G4NReact\MsCatalogMagento2\Console\Command
 */
class ReindexCategory extends AbstractReindex
{
    /**
     * @var CategoryPuller
     */
    protected $categoryPuller;

    /**
     * ReindexCategory constructor
     *
     * @param CategoryPuller $categoryPuller
     * @param MsCatalogHelper $msCatalogHelper
     * @param Emulation $emulation
     * @param AppState $appState
     * @param string|null $name
     */
    public function __construct(
        CategoryPuller $categoryPuller,
        MsCatalogHelper $msCatalogHelper,
        Emulation $emulation,
        AppState $appState,
        ?string $name = null
    ) {
        $this->categoryPuller = $categoryPuller;
        parent::__construct($msCatalogHelper, $emulation, $appState, $name);
    }

    /**
     * @return string
     */
    public function getCommandName(): string
    {
        return 'g4nreact:reindex:category';
    }

    /**
     * @return string
     */
    public function getCommandDescription(): string
    {
        return 'Reindexes categories';
    }

    /**
     * @return CategoryPuller
     */
    public function getPuller(): CategoryPuller
    {
        return $this->categoryPuller;
    }
}
