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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatusReport;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * Reads how complete the search indices are, so that tasks depending on the index (reports, exports, imports) can
 * check it or wait for it. Every call reads the live state; nothing is cached.
 */
interface IndexStatusServiceInterface
{
    /**
     * @throws Exception
     */
    public function getAll(): IndexStatusReport;

    /**
     * @throws Exception
     */
    public function getStatusForClassDefinition(ClassDefinition $classDefinition): IndexStatus;

    /**
     * @throws Exception
     */
    public function getAssetStatus(): IndexStatus;

    /**
     * @throws Exception
     */
    public function getDocumentStatus(): IndexStatus;

    /**
     * @throws Exception
     */
    public function getDataObjectFolderStatus(): IndexStatus;

    /**
     * @param string $indexName the alias name (e.g. "pimcore_asset", "pimcore_data-object_car") or a class name
     *
     * @throws IndexStatusException when no index of that name is known
     * @throws Exception
     */
    public function getStatusByIndexName(string $indexName): IndexStatus;

    /**
     * Polls the status until the index is complete.
     *
     * @throws IndexStatusException when the index is still incomplete after $timeoutSeconds, or is unknown
     * @throws Exception
     */
    public function waitUntilComplete(string $indexName, int $timeoutSeconds, int $pollIntervalSeconds = 5): IndexStatus;
}
