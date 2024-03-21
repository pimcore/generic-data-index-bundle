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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\StandardField\Document\DocumentStandardField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandlerService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\ElementNormalizerTrait;
use Pimcore\Model\Document;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class DocumentNormalizer implements NormalizerInterface
{
    use ElementNormalizerTrait;

    public function __construct(
        private readonly DocumentTypeSerializationHandlerService $documentTypeSerializationHandlerService,
        private readonly WorkflowServiceInterface $workflowService,
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $document = $object;

        if ($document instanceof Document\Folder) {
            return $this->normalizeFolder($document);
        }

        if ($document instanceof Document) {
            return $this->normalizeDocument($document);
        }

        return [];
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Document;
    }

    private function normalizeFolder(Document\Folder $folder): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($folder),
            FieldCategory::STANDARD_FIELDS->value => [],
        ];
    }

    private function normalizeDocument(Document $document): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($document),
            FieldCategory::STANDARD_FIELDS->value => $this->normalizeStandardFields($document),
        ];
    }

    private function normalizeSystemFields(Document $document): array
    {
        $systemFields = [
            SystemField::ID->value => $document->getId(),
            SystemField::PARENT_ID->value => $document->getParentId(),
            SystemField::CREATION_DATE->value => $this->formatTimestamp($document->getCreationDate()),
            SystemField::MODIFICATION_DATE->value => $this->formatTimestamp($document->getModificationDate()),
            SystemField::PUBLISHED->value => $document->isPublished(),
            SystemField::TYPE->value => $document->getType(),
            SystemField::KEY->value => $document->getKey(),
            SystemField::PATH->value => $document->getPath(),
            SystemField::FULL_PATH->value => $document->getRealFullPath(),
            SystemField::PATH_LEVELS->value => $this->extractPathLevels($document),
            SystemField::TAGS->value => $this->extractTagIds($document),
            SystemField::USER_OWNER->value => $document->getUserOwner(),
            SystemField::USER_MODIFICATION->value => $document->getUserModification(),
            SystemField::LOCKED->value => $document->getLocked(),
            SystemField::IS_LOCKED->value => $document->isLocked(),
            SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value =>
                $this->workflowService->hasWorkflowWithPermissions($document),
        ];

        if ($handler = $this->documentTypeSerializationHandlerService->getSerializationHandler($document->getType())) {
            $systemFields = array_merge($systemFields, $handler->getAdditionalSystemFields($document));
        }

        return $systemFields;
    }

    private function normalizeStandardFields(Document $document): array
    {
        $standardFields = [];
        $fieldNames = [
            DocumentStandardField::NAVIGATION_TITLE->value,
            DocumentStandardField::NAVIGATION_NAME->value,
        ];
        $properties = $document->getProperties();
        foreach ($fieldNames as $fieldName) {
            if (isset($properties[$fieldName])) {
                $standardFields[$fieldName] = $properties[$fieldName]->getData();
            }
        }

        return $standardFields;
    }
}
