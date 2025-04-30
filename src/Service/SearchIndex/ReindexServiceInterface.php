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

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface ReindexServiceInterface
{
    public function reindexAll(): ReindexServiceInterface;

    public function reindexAllIndices(): ReindexServiceInterface;

    public function reindexClassDefinitions(bool $enqueueElements = true): ReindexServiceInterface;

    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $enqueueElements = true
    ): ReindexService;

    public function reindexAssets(bool $enqueueElements = true): ReindexServiceInterface;

    public function reindexDocuments(bool $enqueueElements = true): ReindexServiceInterface;
}
