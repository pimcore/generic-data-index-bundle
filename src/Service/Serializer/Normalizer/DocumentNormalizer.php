<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\StandardField\Document\DocumentStandardField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency\DependencyServiceInterface;
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
        private readonly DependencyServiceInterface $dependencyService,
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $skipLazyLoadedFields = SerializerContext::SKIP_LAZY_LOADED_FIELDS->containedInContext($context);

        $document = $object;

        if ($document instanceof Document\Folder) {
            return $this->normalizeFolder($document, $skipLazyLoadedFields);
        }

        if ($document instanceof Document) {
            return $this->normalizeDocument($document, $skipLazyLoadedFields);
        }

        return [];
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Document;
    }

    private function normalizeFolder(Document\Folder $folder, bool $skipLazyLoadedFields): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($folder, $skipLazyLoadedFields),
            FieldCategory::STANDARD_FIELDS->value => [],
        ];
    }

    private function normalizeDocument(Document $document, bool $skipLazyLoadedFields): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($document, $skipLazyLoadedFields),
            FieldCategory::STANDARD_FIELDS->value => $this->normalizeStandardFields($document),
        ];
    }

    private function normalizeSystemFields(Document $document, bool $skipLazyLoadedFields): array
    {
        $pathLevels = $this->extractPathLevels($document);

        $systemFields = [
            SystemField::ID->value => $document->getId(),
            SystemField::ELEMENT_TYPE->value => ElementType::DOCUMENT->value,
            SystemField::PARENT_ID->value => $document->getParentId(),
            SystemField::CREATION_DATE->value => $this->formatTimestamp($document->getCreationDate()),
            SystemField::MODIFICATION_DATE->value => $this->formatTimestamp($document->getModificationDate()),
            SystemField::PUBLISHED->value => $document->isPublished(),
            SystemField::TYPE->value => $document->getType(),
            SystemField::KEY->value => $document->getKey(),
            SystemField::PATH->value => $document->getPath(),
            SystemField::FULL_PATH->value => $document->getRealFullPath(),
            SystemField::USER_OWNER->value => $document->getUserOwner(),
            SystemField::USER_MODIFICATION->value => $document->getUserModification(),
            SystemField::LOCKED->value => $document->getLocked(),
            SystemField::IS_LOCKED->value => $document->isLocked(),
        ];

        if ($handler = $this->documentTypeSerializationHandlerService->getSerializationHandler($document->getType())) {
            $systemFields = array_merge($systemFields, $handler->getAdditionalSystemFields($document));
        }

        if (!$skipLazyLoadedFields) {
            $systemFields = array_merge($systemFields, [
                SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value =>
                    $this->workflowService->hasWorkflowWithPermissions($document),
                SystemField::DEPENDENCIES->value => $this->dependencyService->getRequiresDependencies($document),
                SystemField::PATH_LEVELS->value => $pathLevels,
                SystemField::PATH_LEVEL->value => count($pathLevels),
                SystemField::TAGS->value => $this->extractTagIds($document),
                SystemField::PARENT_TAGS->value => $this->extractParentTagIds($document),
            ]);
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
