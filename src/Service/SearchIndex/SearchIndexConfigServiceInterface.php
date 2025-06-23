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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

/**
 * @internal
 */
interface SearchIndexConfigServiceInterface
{
    public function getClientType(): string;

    /**
     * returns index name for given class name
     */
    public function getIndexName(string $name, bool $isClass = false): string;

    /**
     * return index name without any prefix or suffix
     */
    public function getShortIndexName(string $name): string;

    public function prefixIndexName(string $indexName): string;

    public function getIndexPrefix(): string;

    public function getIndexSettings(): array;

    public function getSearchSettings(): array;

    public function getSearchAnalyzerAttributes(): array;

    public function getMaxSynchronousChildrenRenameLimit(): int;

    public function getSystemFieldsSettings(string $elementType): array;
}
