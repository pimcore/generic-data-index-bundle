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
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingChangeStrategy;
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
     *
     * @throws JsonException when the new mapping cannot be encoded as JSON
     */
    public function diff(string $aliasName, array $mappingProperties): MappingDiff;

    /**
     * Copies the documents into the other index with the new mapping, as the configured
     * `reindex_settings.mapping_change_strategy` says:
     * - "recreate": the documents are copied as they are; MAPPING_INCOMPATIBLE when their old values
     *   do not fit, so that the caller recreates the index and rebuilds it from the database.
     * - "copy": the fields of the diff are removed from the copy. Fields named in parsing failures
     *   of a copy attempt are removed too, and the copy is retried a bounded number of times.
     *   MAPPING_INCOMPATIBLE is returned only when no further field can be removed.
     * Genuine errors (unreachable cluster, timeouts, non-parsing failures) are thrown.
     *
     * @throws Exception
     */
    public function copy(string $aliasName, array $mappingProperties): IndexCopyResult;

    public function getMappingChangeStrategy(): MappingChangeStrategy;
}
