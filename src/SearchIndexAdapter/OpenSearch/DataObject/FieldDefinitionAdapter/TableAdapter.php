<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

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
