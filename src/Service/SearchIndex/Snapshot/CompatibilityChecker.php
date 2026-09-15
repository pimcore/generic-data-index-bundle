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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
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
        $seenClassIds = [];
        foreach ($manifest->classMappingChecksums as $classId => $manifestChecksum) {
            $classId = (string) $classId;
            $seenClassIds[$classId] = true;
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

        // A class index whose class has no entry in the manifest's class_mapping_checksums (an
        // older snapshot, or a manifest hand-edited to drop it) cannot be verified at all: treat
        // it as UNVERIFIED rather than silently importable, so the gate closes the same way an
        // INCOMPATIBLE checksum would.
        foreach ($manifest->indices as $index) {
            if ($index->elementType !== ElementType::DATA_OBJECT->value || $index->classId === null) {
                continue;
            }
            $classId = (string) $index->classId;
            if (isset($seenClassIds[$classId]) || array_key_exists($classId, $manifest->classMappingChecksums)) {
                continue;
            }
            $seenClassIds[$classId] = true;
            $classes[] = new ClassCompatibility(
                $classId,
                ClassDefinition::getById($classId)?->getName(),
                ClassCompatibilityStatus::UNVERIFIED,
                0,
                $this->settingsStoreService->getClassMappingCheckSum($classId),
                null
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
