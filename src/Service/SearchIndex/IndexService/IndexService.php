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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class IndexService implements IndexServiceInterface
{
    use LoggerAwareTrait;

    private bool $performIndexRefresh = false;

    public function __construct(
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly OpenSearchServiceInterface $openSearchService,
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function isPerformIndexRefresh(): bool
    {
        return $this->performIndexRefresh;
    }

    public function setPerformIndexRefresh(bool $performIndexRefresh): IndexService
    {
        $this->performIndexRefresh = $performIndexRefresh;

        return $this;
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
            $indexDocument = $this->openSearchService->getDocument($indexName, $element->getId());
            $originalChecksum =
                $indexDocument['_source'][FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] ?? -1;
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $originalChecksum = -1;
        }

        $indexData = $this->getIndexData($element);

        if ($indexData[FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] !== $originalChecksum) {

            $this->bulkOperationService->addUpdate(
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

        $this->bulkOperationService->addDeletion(
            $indexName,
            $elementId
        );

        $this->logger->info('Add deletion of item ID ' . $elementId . ' from ' . $indexName . ' index to bulk.');

        return $this;
    }

    public function updateAssetDependencies(Asset $asset): array
    {
        $elementsToUpdate = [];
        foreach ($asset->getDependencies()->getRequiredBy() as $requiredByEntry) {
            $element = null;
            if ($requiredByEntry['type'] === 'object') {
                $element = AbstractObject::getById($requiredByEntry['id']);
            }
            if ($requiredByEntry['type'] === 'asset') {
                $element = Asset::getById($requiredByEntry['id']);
            }
            if ($element instanceof ElementInterface) {
                $elementsToUpdate[] = $element;
            }
        }

        return $elementsToUpdate;
    }

    /**
     * @throws IndexDataException
     */
    private function getIndexData(ElementInterface $element): array
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

            $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields], JSON_THROW_ON_ERROR));
            $systemFields[SystemField::CHECKSUM->value] = $checksum;

            return [
                FieldCategory::SYSTEM_FIELDS->value => $systemFields,
                FieldCategory::STANDARD_FIELDS->value => $standardFields,
                FieldCategory::CUSTOM_FIELDS->value => $customFields,
            ];
        } catch (Exception|ExceptionInterface $e) {
            throw new IndexDataException($e->getMessage());
        }

    }
}
