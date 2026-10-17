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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface IndexHandlerInterface
{
    /**
     * @throws Exception
     */
    public function updateMapping(
        mixed $context = null,
        bool $forceCreateIndex = false,
        ?array $mappingProperties = null
    ): void;

    /**
     * Applies the mapping through a copy into the other -odd/-even index. Fields whose old values
     * do not fit the new mapping are left out of the copy; the result says which, and whether the
     * index had to be created empty instead, so callers can queue the rebuild from the database.
     *
     * @throws Exception
     * @throws ReindexFailedException
     */
    public function reindexMapping(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): MappingUpdateResult;

    /**
     * Fields of the live index whose mapping differs from the current mapping, classified into
     * kept and stripped as the next copy would handle them. Empty when the index does not exist.
     */
    public function getMappingDiff(?ClassDefinition $context = null): MappingDiff;

    public function deleteIndex(mixed $context): void;

    public function getCurrentFullIndexName(mixed $context = null): string;

    public function getClassMappingCheckSum(array $properties): int;

    public function getMappingProperties(mixed $context): array;
}
