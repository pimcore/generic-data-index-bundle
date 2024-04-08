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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;

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

    public function deleteIndex(mixed $context): void;

    public function getCurrentFullIndexName(mixed $context = null): string;

    public function getClassMappingCheckSum(array $properties): int;

    public function getMappingProperties(mixed $context): array;
}
