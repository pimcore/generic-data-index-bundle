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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Exception;

/**
 * @internal
 */
interface SettingsStoreServiceInterface
{
    public function getClassMappingCheckSum(
        string $classDefinitionId
    ): ?int;

    /**
     * @throws Exception
     */
    public function storeClassMapping(
        string $classDefinitionId,
        int $data
    ): void;

    public function removeClassMapping(
        string $classDefinitionId
    ): void;

    /**
     * Checksum of the workflowPlaces mapping applied to the index by the last index update.
     */
    public function getWorkflowPlacesMappingChecksum(string $indexName): ?int;

    /**
     * @throws Exception
     */
    public function storeWorkflowPlacesMappingChecksum(string $indexName, int $data): void;
}
