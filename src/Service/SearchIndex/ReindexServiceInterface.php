<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface ReindexServiceInterface
{
    public function reindexAll(): ReindexServiceInterface;

    public function reindexClassDefinitions(): ReindexServiceInterface;

    public function reindexClassDefinition(ClassDefinition $classDefinition): ReindexServiceInterface;

    public function reindexAssets(): ReindexServiceInterface;

    public function reindexDocuments(): ReindexServiceInterface;
}