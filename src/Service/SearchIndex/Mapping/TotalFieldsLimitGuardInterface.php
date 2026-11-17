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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\TotalFieldsLimitException;

/**
 * Refuses a mapping declaring more fields than the index allows before the engine is asked for it.
 *
 * The engine's own check cannot be relied on: it runs after the mapping has been parsed into the heap of the
 * cluster manager, so a mapping far enough over the limit kills that node instead of answering with an error
 * (measured: a ~755,000 field classification store mapping on a 1 GB heap died in ObjectMapper$TypeParser).
 *
 * @internal
 */
interface TotalFieldsLimitGuardInterface
{
    /**
     * Counted in the unit the engine checks, see {@see MappingFieldCountServiceInterface::countFields()}. Does
     * nothing when the configuration names no limit: then the engine is the only one who can refuse.
     *
     * @param array<string, mixed> $mappingProperties
     *
     * @throws TotalFieldsLimitException
     */
    public function assertWithinLimit(string $indexName, array $mappingProperties): void;
}
