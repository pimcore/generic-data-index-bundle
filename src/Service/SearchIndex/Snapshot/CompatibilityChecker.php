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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ClassCompatibility;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\CompatibilityReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final class CompatibilityChecker implements CompatibilityCheckerInterface
{
    public function __construct(
        private readonly SettingsStoreServiceInterface $settingsStoreService,
        private readonly DataObjectIndexHandler $dataObjectIndexHandler,
    ) {
    }

    public function check(Manifest $manifest): CompatibilityReport
    {
        $classes = [];
        foreach ($manifest->classMappingChecksums as $classId => $manifestChecksum) {
            $classId = (string) $classId;
            $classDefinition = ClassDefinition::getById($classId);
            if ($classDefinition === null) {
                $classes[] = new ClassCompatibility(
                    $classId,
                    null,
                    ClassCompatibilityStatus::MISSING_LOCALLY,
                    $manifestChecksum,
                    null,
                    null
                );

                continue;
            }

            $stored = $this->settingsStoreService->getClassMappingCheckSum($classId);
            if ($stored === $manifestChecksum) {
                $classes[] = new ClassCompatibility(
                    $classId,
                    $classDefinition->getName(),
                    ClassCompatibilityStatus::COMPATIBLE,
                    $manifestChecksum,
                    $stored,
                    null
                );

                continue;
            }

            $computed = $this->dataObjectIndexHandler->getClassMappingCheckSum(
                $this->dataObjectIndexHandler->getMappingProperties($classDefinition)
            );
            $status = $computed === $manifestChecksum
                ? ClassCompatibilityStatus::STALE_STORE
                : ClassCompatibilityStatus::INCOMPATIBLE;
            $classes[] = new ClassCompatibility(
                $classId,
                $classDefinition->getName(),
                $status,
                $manifestChecksum,
                $stored,
                $computed
            );
        }

        $missingInManifest = [];
        foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
            if (!array_key_exists($classDefinition->getId(), $manifest->classMappingChecksums)) {
                $missingInManifest[] = $classDefinition->getName();
            }
        }

        return new CompatibilityReport($classes, $missingInManifest);
    }
}
