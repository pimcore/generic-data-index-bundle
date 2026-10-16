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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;

/**
 * Copies an index into the other -odd/-even index with a new mapping, leaving out the fields
 * whose old values do not fit the new mapping, so the live index never has to be recreated
 * for a field type change.
 *
 * @internal
 */
interface IndexCopyServiceInterface
{
    /**
     * Fields of the live index behind the alias whose mapping differs from the new mapping.
     */
    public function diff(string $aliasName, array $mappingProperties): MappingDiff;

    /**
     * Copies the documents with the fields of the diff removed. Fields named in parsing failures
     * of a copy attempt are removed too, and the copy is retried a bounded number of times.
     * MAPPING_INCOMPATIBLE is returned only when no further field can be removed; genuine errors
     * (unreachable cluster, timeouts, non-parsing failures) are thrown.
     *
     * @throws Exception
     */
    public function copy(string $aliasName, array $mappingProperties): IndexCopyResult;
}
