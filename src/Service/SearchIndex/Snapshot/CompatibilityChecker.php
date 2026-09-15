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
            $classes[] = $this->checkClass($classId, (int) $manifestChecksum);
        }

        // A class index whose class has no entry in the manifest's class_mapping_checksums (an
        // older snapshot, or a manifest hand-edited to drop it) cannot be verified at all: treat
        // it as UNVERIFIED rather than silently importable, so the gate closes the same way an
        // INCOMPATIBLE checksum would. A class that no longer exists locally at all is a
        // different situation: the importer always skips it as "no local counterpart",
        // independent of --force, so it must not gate the import as UNVERIFIED either.
        foreach ($this->collectUncheckedClassIndices($manifest, $seenClassIds) as $unverifiedClass) {
            $classes[] = $unverifiedClass;
        }

        $missingInManifest = $this->collectClassesMissingInManifest($manifest);

        return new CompatibilityReport($classes, $missingInManifest);
    }

    private function checkClass(string $classId, int $manifestChecksum): ClassCompatibility
    {
        $classDefinition = ClassDefinition::getById($classId);
        if ($classDefinition === null) {
            return new ClassCompatibility(
                $classId,
                null,
                ClassCompatibilityStatus::MISSING_LOCALLY,
                $manifestChecksum,
                null,
                null,
            );
        }

        $stored = $this->settingsStoreService->getClassMappingCheckSum($classId);
        if ($stored === $manifestChecksum) {
            return new ClassCompatibility(
                $classId,
                $classDefinition->getName(),
                ClassCompatibilityStatus::COMPATIBLE,
                $manifestChecksum,
                $stored,
                null,
            );
        }

        $computed = $this->dataObjectIndexHandler->getClassMappingCheckSum(
            $this->dataObjectIndexHandler->getMappingProperties($classDefinition),
        );
        $status = $computed === $manifestChecksum
            ? ClassCompatibilityStatus::STALE_STORE
            : ClassCompatibilityStatus::INCOMPATIBLE;

        return new ClassCompatibility(
            $classId,
            $classDefinition->getName(),
            $status,
            $manifestChecksum,
            $stored,
            $computed,
        );
    }

    /**
     * @param array<string, true> $seenClassIds class ids already checked from the manifest's
     *                                           class_mapping_checksums, keyed by class id
     *
     * @return ClassCompatibility[]
     */
    private function collectUncheckedClassIndices(Manifest $manifest, array $seenClassIds): array
    {
        $classes = [];
        foreach ($manifest->indices as $index) {
            if ($index->elementType !== ElementType::DATA_OBJECT->value || $index->classId === null) {
                continue;
            }
            $classId = (string) $index->classId;
            if (isset($seenClassIds[$classId]) || array_key_exists($classId, $manifest->classMappingChecksums)) {
                continue;
            }
            $seenClassIds[$classId] = true;
            $classDefinition = ClassDefinition::getById($classId);
            if ($classDefinition === null) {
                $classes[] = new ClassCompatibility(
                    $classId,
                    null,
                    ClassCompatibilityStatus::MISSING_LOCALLY,
                    0,
                    null,
                    null,
                );

                continue;
            }
            $classes[] = new ClassCompatibility(
                $classId,
                $classDefinition->getName(),
                ClassCompatibilityStatus::UNVERIFIED,
                0,
                $this->settingsStoreService->getClassMappingCheckSum($classId),
                null,
            );
        }

        return $classes;
    }

    /**
     * @return string[] class names
     */
    private function collectClassesMissingInManifest(Manifest $manifest): array
    {
        $missingInManifest = [];
        foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
            if (!array_key_exists($classDefinition->getId(), $manifest->classMappingChecksums)) {
                $missingInManifest[] = $classDefinition->getName();
            }
        }

        return $missingInManifest;
    }
}
