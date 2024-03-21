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

/**
 * @internal
 */
final class SearchIndexConfigService implements SearchIndexConfigServiceInterface
{
    use LoggerAwareTrait;

    private const SYSTEM_FIELD_GENERAL = 'general';

    public const SYSTEM_FIELD_ASSET = 'asset';

    public const SYSTEM_FIELD_DOCUMENT = 'document';

    public const SYSTEM_FIELD_DATA_OBJECT = 'data_object';

    public function __construct(
        private readonly string $indexPrefix,
        private readonly array $indexSettings,
        private readonly array $searchSettings,
        private readonly array $systemFieldsSettings,
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

    public function getSearchAnalyzerAttributes(): array
    {
        return $this->searchSettings['search_analyzer_attributes'] ?? [];
    }

    public function getMaxSynchronousChildrenRenameLimit(): int
    {
        return $this->searchSettings['max_synchronous_children_rename_limit'] ?? 0;
    }

    public function getSystemFieldsSettings(string $elementType): array
    {
        $systemFieldsSettings = array_merge(
            $this->systemFieldsSettings[self::SYSTEM_FIELD_GENERAL],
            $this->systemFieldsSettings[$elementType] ?? []
        );

        foreach($systemFieldsSettings as &$systemFieldsSetting) {
            if (!count($systemFieldsSetting['properties'])) {
                unset($systemFieldsSetting['properties']);
            }
            if (!count($systemFieldsSetting['fields'])) {
                unset($systemFieldsSetting['fields']);
            }
        }

        return $systemFieldsSettings;
    }
}
