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
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Exception\ClassificationStoreEngineNotAvailableException;
use Pimcore\Model\DataObject\Exception\UnknownClassificationStoreEngineException;

/**
 * The only place the bundle asks core which engine a store resolves to. The capability itself belongs
 * to the engine and is not restated here, so the two cannot drift apart.
 *
 * @internal
 */
final readonly class EngineCapabilityService implements EngineCapabilityServiceInterface
{
    public function __construct(
        private EngineResolver $engineResolver,
    ) {
    }

    public function supports(?int $storeId, EngineFeature $feature): bool
    {
        try {
            return $this->engineResolver->resolveForStore($storeId)->supports($feature);
        } catch (UnknownClassificationStoreEngineException|ClassificationStoreEngineNotAvailableException) {
            // an engine this installation does not provide holds nothing: the key is dropped and reported
            // from the mapping path like any other refusal, rather than failing the mapping build
            return false;
        }
    }
}
