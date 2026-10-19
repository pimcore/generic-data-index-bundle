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

    public function getIndexState(string $key): ?string;

    /**
     * @throws Exception
     */
    public function storeIndexState(string $key, string $value): void;

    public function removeIndexState(string $key): void;

    /**
     * Replaces the stored value only while it still equals $expected, in one statement.
     *
     * @return bool whether the value was replaced
     *
     * @throws Exception
     */
    public function replaceIndexState(string $key, string $expected, string $value): bool;

    /**
     * Removes the stored value only while it still equals $expected, in one statement.
     *
     * @return bool whether the value was removed
     *
     * @throws Exception
     */
    public function removeIndexStateIfEquals(string $key, string $expected): bool;
}
