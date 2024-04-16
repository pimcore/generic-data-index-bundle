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
