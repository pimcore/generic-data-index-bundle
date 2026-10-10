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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class IndexService implements IndexServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
    ) {
    }

    /**
     * @throws IndexDataException
     */
    public function updateIndexData(ElementInterface $element): IndexService
    {
        $indexName = $this->typeAdapterService
            ->getTypeAdapter($element)
            ->getAliasIndexNameByElement($element);

        try {
            $indexDocument = $this->searchIndexService->getDocument(
                index: $indexName,
                id: $element->getId(),
                ignore404: true
            );
            $originalChecksum =
                $indexDocument['_source'][FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] ?? -1;
        } catch (Exception $e) {
            // Could not read the existing document to compare checksums; fall back to always
            // writing. Log with context + the exception so the cause is not lost.
            $this->logger->error('Failed to read existing index document for checksum comparison', [
                'indexName' => $indexName,
                'elementId' => $element->getId(),
                'exception' => $e,
            ]);
            $originalChecksum = -1;
        }

        $indexData = $this->getIndexData($element, $indexName);

        if ($indexData[FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] !== $originalChecksum) {

            $this->bulkOperationService->add(
                $indexName,
                $element->getId(),
                $indexData
            );

            $this->logger->info(
                sprintf(
                    'Add update of element ID %s from %s index to bulk.',
                    $element->getId(),
                    $indexName
                )
            );
        } else {
            $this->logger->info(
                sprintf(
                    'Not updating index %s for element ID %s - nothing has changed.',
                    $indexName,
                    $element->getId()
                )
            );
        }

        return $this;
    }

    public function deleteFromIndex(ElementInterface $element): IndexService
    {
        $indexName = $this->typeAdapterService
            ->getTypeAdapter($element)
            ->getAliasIndexNameByElement($element);

        $elementId = $element->getId();

        return $this->deleteFromSpecificIndex($indexName, $elementId);
    }

    public function deleteFromSpecificIndex(string $indexName, int $elementId): IndexService
    {
        $this->bulkOperationService->addDeletion(
            $indexName,
            $elementId
        );

        $this->logger->notice('Add deletion of item ID ' . $elementId . ' from ' . $indexName . ' index to bulk.');

        return $this;
    }

    /**
     * @throws IndexDataException
     */
    private function getIndexData(ElementInterface $element, string $indexName): array
    {
        try {
            $typeAdapter = $this->typeAdapterService->getTypeAdapter($element);
            $indexData = $typeAdapter
                ->getNormalizer()
                ->normalize($element);

            $systemFields = $indexData[FieldCategory::SYSTEM_FIELDS->value];
            $standardFields = $indexData[FieldCategory::STANDARD_FIELDS->value];
            $customFields = [];

            //dispatch event before building checksum
            $updateIndexDataEvent = $typeAdapter->getUpdateIndexDataEvent($element, $customFields);
            $this->eventDispatcher->dispatch($updateIndexDataEvent);
            $customFields = $updateIndexDataEvent->getCustomFields();

            $checksumData = [$systemFields, $standardFields, $customFields];
            if (isset($systemFields[SystemField::WORKFLOW_PLACES->value])) {
                // Places stored before their workflow was part of the index mapping are not searchable: the
                // document has to be written again once the applied workflowPlaces mapping changes.
                $checksumData[] = $this->settingsStoreService->getWorkflowPlacesMappingChecksum($indexName);
            }

            $checksum = crc32(json_encode($checksumData, JSON_THROW_ON_ERROR));
            $systemFields[SystemField::CHECKSUM->value] = $checksum;

            return [
                FieldCategory::SYSTEM_FIELDS->value => $systemFields,
                FieldCategory::STANDARD_FIELDS->value => $standardFields,
                FieldCategory::CUSTOM_FIELDS->value => $customFields,
            ];
        } catch (Exception|ExceptionInterface $e) {
            throw new IndexDataException($e->getMessage(), 0, $e);
        }

    }
}
