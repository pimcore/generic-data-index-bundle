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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexIdentity;
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

        // Class-mapping updates are dispatched asynchronously, so the stored checksum can lag
        // behind the actual class definition. Always compute the checksum from the current
        // definition and let it decide compatibility; a stored value that happens to match the
        // manifest is not proof the mapping is still compatible.
        $stored = $this->settingsStoreService->getClassMappingCheckSum($classId);
        $computed = $this->dataObjectIndexHandler->getClassMappingCheckSum(
            $this->dataObjectIndexHandler->getMappingProperties($classDefinition),
        );

        return new ClassCompatibility(
            $classId,
            $classDefinition->getName(),
            $this->resolveStatus($computed, $stored, $manifestChecksum),
            $manifestChecksum,
            $stored,
            $computed,
        );
    }

    private function resolveStatus(int $computed, ?int $stored, int $manifestChecksum): ClassCompatibilityStatus
    {
        if ($computed !== $manifestChecksum) {
            return ClassCompatibilityStatus::INCOMPATIBLE;
        }

        return $stored === $manifestChecksum
            ? ClassCompatibilityStatus::COMPATIBLE
            : ClassCompatibilityStatus::STALE_STORE;
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
            if (!IndexIdentity::fromManifestIndex($index)->isClassIndex()) {
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
                    null,
                    null,
                    null,
                );

                continue;
            }
            $classes[] = new ClassCompatibility(
                $classId,
                $classDefinition->getName(),
                ClassCompatibilityStatus::UNVERIFIED,
                null,
                $this->settingsStoreService->getClassMappingCheckSum($classId),
                null,
            );
        }

        return $classes;
    }

    /**
     * A class is "missing in the manifest" when the snapshot has no data-object index entry for
     * it, independent of whether it happens to have a class_mapping_checksums entry: a checksum
     * without an index entry still means the class's old local index is left untouched by the
     * import, which the operator needs to know about.
     *
     * @return string[] class names
     */
    private function collectClassesMissingInManifest(Manifest $manifest): array
    {
        $classIdsWithIndex = array_flip($this->classIdsWithDataObjectIndex($manifest));
        $missingInManifest = [];
        foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
            if (!array_key_exists((string) $classDefinition->getId(), $classIdsWithIndex)) {
                $missingInManifest[] = $classDefinition->getName();
            }
        }

        return $missingInManifest;
    }

    /**
     * @return string[] class ids that have a data-object index entry in the manifest
     */
    private function classIdsWithDataObjectIndex(Manifest $manifest): array
    {
        $classIds = [];
        foreach ($manifest->indices as $index) {
            if (IndexIdentity::fromManifestIndex($index)->isClassIndex()) {
                $classIds[] = (string) $index->classId;
            }
        }

        return $classIds;
    }
}
