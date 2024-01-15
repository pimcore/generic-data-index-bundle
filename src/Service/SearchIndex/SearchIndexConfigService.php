<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class SearchIndexConfigService
{
    use LoggerAwareTrait;

    public function __construct(
        protected string $indexPrefix,
        protected array $indexSettings,
        protected array $searchSettings,
    ) {
    }

    /**
     * returns index name for given class name
     *
     * @param string $name
     *
     * @return string
     */
    public function getIndexName(string $name): string
    {
        return $this->getIndexPrefix() . strtolower($name);
    }

    public function prefixIndexName(string $indexName): string
    {
        return $this->getIndexPrefix() . $indexName;
    }

    /**
     * @return string
     */
    public function getIndexPrefix(): string
    {
        return $this->indexPrefix;
    }

    /**
     * @return array
     */
    public function getIndexSettings()
    {
        return $this->indexSettings;
    }

    /**
     * @return array
     */
    public function getSearchSettings()
    {
        return $this->searchSettings;
    }

    /**
     * @return int
     */
    public function getMaxSynchronousChildrenRenameLimit(): int
    {
        return $this->searchSettings['max_synchronous_children_rename_limit'] ?? 0;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
