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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataProviderServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandlerService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\ElementNormalizerTrait;
use Pimcore\Model\Asset;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class AssetNormalizer implements NormalizerInterface
{
    use ElementNormalizerTrait;

    public function __construct(
        private readonly AssetTypeSerializationHandlerService $assetTypeSerializationHandlerService,
        private readonly FieldDefinitionServiceInterface $fieldDefinitionService,
        private readonly WorkflowServiceInterface $workflowService,
        private readonly MetadataProviderServiceInterface $metadataProviderService,
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $asset = $object;
        die('AssetNormalizer');
        if ($asset instanceof Asset\Folder) {
            return $this->normalizeFolder($asset);
        }

        if ($asset instanceof Asset) {
            return $this->normalizeAsset($asset);
        }

        return [];
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Asset;
    }

    private function normalizeFolder(Asset\Folder $folder): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($folder),
            FieldCategory::STANDARD_FIELDS->value => [],
        ];
    }

    private function normalizeAsset(Asset $asset): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($asset),
            FieldCategory::STANDARD_FIELDS->value => $this->normalizeStandardFields($asset),
        ];
    }

    private function normalizeSystemFields(Asset $asset): array
    {
        $systemFields = [
            SystemField::ID->value => $asset->getId(),
            SystemField::PARENT_ID->value => $asset->getParentId(),
            SystemField::CREATION_DATE->value => $this->formatTimestamp($asset->getCreationDate()),
            SystemField::MODIFICATION_DATE->value => $this->formatTimestamp($asset->getModificationDate()),
            SystemField::TYPE->value => $asset->getType(),
            SystemField::KEY->value => $asset->getKey(),
            SystemField::PATH->value => $asset->getPath(),
            SystemField::FULL_PATH->value => $asset->getRealFullPath(),
            SystemField::PATH_LEVELS->value => $this->extractPathLevels($asset),
            SystemField::TAGS->value => $this->extractTagIds($asset),
            SystemField::MIME_TYPE->value => $asset->getMimeType(),
            SystemField::USER_OWNER->value => $asset->getUserOwner(),
            SystemField::USER_MODIFICATION->value => $asset->getUserModification(),
            SystemField::LOCKED->value => $asset->getLocked(),
            SystemField::IS_LOCKED->value => $asset->isLocked(),
            SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value =>
                $this->workflowService->hasWorkflowWithPermissions($asset),
            SystemField::FILE_SIZE->value => $asset->getFileSize(),
        ];

        if ($handler = $this->assetTypeSerializationHandlerService->getSerializationHandler($asset->getType())) {
            $systemFields = array_merge($systemFields, $handler->getAdditionalSystemFields($asset));
        }

        return $systemFields;
    }

    private function normalizeStandardFields(Asset $asset): array
    {
        $result = [];

        foreach($this->metadataProviderService->getSearchableMetaDataForAsset($asset) as $metadata) {
            $data = $metadata['data'];
            $language = $metadata['language'] ?? null;
            $language = $language ?: MappingProperty::NOT_LOCALIZED_KEY;
            $result[$metadata['name']] = $result[$metadata['name']] ?? [];
            $result[$metadata['name']][$language] = $this->fieldDefinitionService->normalizeValue(
                $metadata['type'],
                $data
            );
        }

        return $result;
    }
}
