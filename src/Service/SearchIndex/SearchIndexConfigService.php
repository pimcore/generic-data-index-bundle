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

class SearchIndexConfigService
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly string $indexPrefix,
        protected readonly array $indexSettings,
        protected readonly array $searchSettings,
    ) {
    }

    /**
     * returns index name for given class name
     */
    public function getIndexName(string $name): string
    {
        return $this->getIndexPrefix() . strtolower($name);
    }

    public function prefixIndexName(string $indexName): string
    {
        return $this->getIndexPrefix() . $indexName;
    }

    public function getIndexPrefix(): string
    {
        return $this->indexPrefix;
    }

    public function getIndexSettings(): array
    {
        return $this->indexSettings;
    }

    public function getSearchSettings(): array
    {
        return $this->searchSettings;
    }

    public function getMaxSynchronousChildrenRenameLimit(): int
    {
        return $this->searchSettings['max_synchronous_children_rename_limit'] ?? 0;
    }
}
