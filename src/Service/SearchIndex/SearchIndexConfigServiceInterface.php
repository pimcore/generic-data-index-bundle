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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

/**
 * @internal
 */
interface SearchIndexConfigServiceInterface
{
    /**
     * returns index name for given class name
     */
    public function getIndexName(string $name): string;

    public function prefixIndexName(string $indexName): string;

    public function getIndexPrefix(): string;

    public function getIndexSettings(): array;

    public function getSearchSettings(): array;

    public function getSearchAnalyzerAttributes(): array;

    public function getMaxSynchronousChildrenRenameLimit(): int;

    public function getSystemFieldsSettings(string $elementType): array;
}
