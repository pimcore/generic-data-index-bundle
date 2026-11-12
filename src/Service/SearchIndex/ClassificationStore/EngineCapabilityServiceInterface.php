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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;

/**
 * Answers whether the engine behind a classification store can hold what a key declares.
 *
 * The mapping, the document and the inheritance walk all ask it, so a key the engine cannot hold is
 * dropped from the three alike.
 */
interface EngineCapabilityServiceInterface
{
    /**
     * @param int|null $storeId null when the field definition carries no store, which means the default store
     */
    public function supports(?int $storeId, EngineFeature $feature): bool;
}
