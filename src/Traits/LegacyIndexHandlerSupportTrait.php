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

namespace Pimcore\Bundle\GenericDataIndexBundle\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\TotalFieldsLimitGuard;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\TotalFieldsLimitGuardInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionMethod;

/**
 * What an index handler written against the constructor of 2026.3.2 still needs: the collaborators it does not
 * pass, created on first use, and the detection of its own overridden reindexMapping(). Kept out of the handler
 * so that the handler depends on the collaborators it uses, not on the ones it falls back to.
 *
 * @internal
 */
trait LegacyIndexHandlerSupportTrait
{
    private ?IndexCopyServiceInterface $defaultIndexCopyService = null;

    private ?TotalFieldsLimitGuardInterface $defaultTotalFieldsLimitGuard = null;

    private ?bool $reindexMappingOverridden = null;

    private function legacyIndexCopyService(
        SearchIndexServiceInterface $searchIndexService,
        ?IndexLockInterface $indexLock,
        ?LoggerInterface $logger
    ): IndexCopyServiceInterface {
        if ($this->defaultIndexCopyService === null) {
            $this->defaultIndexCopyService = new IndexCopyService(
                searchIndexService: $searchIndexService,
                mappingDiffService: new MappingDiffService(),
                pimcoreGenericDataIndexLogger: $logger ?? new NullLogger(),
                indexLock: $indexLock
            );
        }

        return $this->defaultIndexCopyService;
    }

    /** Stateless, so a handler not passing one keeps the preflight at no cost. */
    private function legacyTotalFieldsLimitGuard(
        SearchIndexConfigServiceInterface $searchIndexConfigService
    ): TotalFieldsLimitGuardInterface {
        if ($this->defaultTotalFieldsLimitGuard === null) {
            $this->defaultTotalFieldsLimitGuard = new TotalFieldsLimitGuard($searchIndexConfigService);
        }

        return $this->defaultTotalFieldsLimitGuard;
    }

    /** Whether reindexMapping() is declared by a subclass rather than by the given base class. */
    private function isReindexMappingOverridden(string $baseClass): bool
    {
        if ($this->reindexMappingOverridden === null) {
            $declaringClass = (new ReflectionMethod($this, 'reindexMapping'))->getDeclaringClass()->getName();
            $this->reindexMappingOverridden = $declaringClass !== $baseClass;
        }

        return $this->reindexMappingOverridden;
    }
}
