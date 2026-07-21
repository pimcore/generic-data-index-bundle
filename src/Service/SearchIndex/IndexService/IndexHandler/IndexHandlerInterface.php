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
     * @throws ReindexFailedException
     */
    public function reindexMapping(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null,
        int $depth = 0
    ): void;

    public function deleteIndex(mixed $context): void;

    public function getCurrentFullIndexName(mixed $context = null): string;

    public function getClassMappingCheckSum(array $properties): int;

    public function getMappingProperties(mixed $context): array;
}
