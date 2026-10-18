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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuildReport;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface ReindexServiceInterface
{
    public function reindexAll(): ReindexServiceInterface;

    public function reindexAllIndices(): ReindexServiceInterface;

    /**
     * Same as {@see reindexAllIndices()}, and reports the indices that were queued for a rebuild
     * because fields were left out of the copy, the index had to be created empty, or a custom index
     * handler applied the mapping without reporting what it did.
     *
     * @throws Exception
     */
    public function reindexAllIndicesWithReport(): IndexRebuildReport;

    public function reindexClassDefinitions(bool $enqueueElements = true): ReindexServiceInterface;

    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $enqueueElements = true
    ): ReindexService;

    public function reindexAssets(bool $enqueueElements = true): ReindexServiceInterface;

    public function reindexDocuments(bool $enqueueElements = true): ReindexServiceInterface;
}
