<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class TableAdapter extends AbstractAdapter
{
    public function __construct(
        protected SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected FieldDefinitionServiceInterface $fieldDefinitionService,
        private readonly IndexMappingServiceInterface $indexMappingService,
    ) {
        parent::__construct(
            $searchIndexConfigService,
            $fieldDefinitionService
        );
    }

    public function getIndexMapping(): array
    {
        if ($this->isColumnConfigActivated() && !$this->hasIntegerColumnsOnly()) {
            $mapping = [
                'type' => 'nested',
                'properties' => [],
            ];

            foreach ($this->getColumnConfig() as $columnConfig) {
                $mapping['properties'][$columnConfig['key']] = $this->indexMappingService->getMappingForTextKeyword(
                    $this->searchIndexConfigService->getSearchAnalyzerAttributes()
                );
            }

            return $mapping;
        }

        return $this->indexMappingService->getMappingForTextKeyword(
            $this->searchIndexConfigService->getSearchAnalyzerAttributes()
        );
    }

    public function normalize(mixed $value): mixed
    {
        $value = parent::normalize($value);

        if (
            !is_array($value)
            || !$this->isColumnConfigActivated()
            || $this->hasIntegerColumnsOnly()
        ) {
            return $value;
        }

        $columnKeys = array_map(
            static fn (array $columnConfig): string => (string)$columnConfig['key'],
            $this->getColumnConfig()
        );

        $columnCount = count($columnKeys);

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row) || !array_is_list($row)) {
                $rows[] = $row;

                continue;
            }

            $rows[] = array_combine(
                $columnKeys,
                array_slice(array_pad($row, $columnCount, null), 0, $columnCount)
            );
        }

        return $rows;
    }

    private function hasIntegerColumnsOnly(): bool
    {
        foreach ($this->getColumnConfig() as $columnConfig) {
            if (filter_var($columnConfig['key'], FILTER_VALIDATE_INT) === false) {
                return false;
            }
        }

        return true;
    }

    private function getColumnConfig(): array
    {
        if (
            property_exists($this->getFieldDefinition(), 'columnConfig')
            && is_array($this->getFieldDefinition()->columnConfig)
        ) {
            return $this->getFieldDefinition()->columnConfig;
        }

        return [];
    }

    private function isColumnConfigActivated(): bool
    {
        return property_exists($this->getFieldDefinition(), 'columnConfigActivated')
            && $this->getFieldDefinition()->columnConfigActivated === true;
    }
}
