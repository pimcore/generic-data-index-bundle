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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer\AbstractElementNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractIndexService implements IndexServiceInterface
{
    use LoggerAwareTrait;

    protected bool $performIndexRefresh = false;

    protected AbstractElementNormalizer $elementNormalizer;

    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly SearchIndexConfigService $searchIndexConfigService,
        protected readonly LanguageService          $languageService,
        protected readonly WorkflowService          $workflowService,
        protected readonly OpenSearchService        $openSearchService,
        protected readonly BulkOperationService     $bulkOperationService,
        protected readonly PathService              $pathUpdateService,
        protected readonly Connection               $dbConnection,
    ) {
    }

    public function rewriteChildrenIndexPaths(ElementInterface $element, string $indexName, string $oldFullPath): void
    {
        $countResult = $this->openSearchService->countByAttributeValue(
            $indexName,
            FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::FULL_PATH->value,
            $oldFullPath
        );

        if ($countResult === 0) {
            return;
        }

        if ($countResult > $this->searchIndexConfigService->getMaxSynchronousChildrenRenameLimit()) {
            $msg = sprintf(
                'Direct rewrite of children paths in OpenSearch was skipped as more than %s items need an update (%s items). The index will be updated asynchronously via index update queue command cronjob.',
                $this->searchIndexConfigService->getMaxSynchronousChildrenRenameLimit(),
                $countResult
            );
            $this->logger->info(
                $msg
            );

            return;
        }

        $this->pathUpdateService->updatePath($indexName, $oldFullPath, $element->getRealFullPath());
    }

    public function isPerformIndexRefresh(): bool
    {
        return $this->performIndexRefresh;
    }

    public function setPerformIndexRefresh(bool $performIndexRefresh): AbstractIndexService
    {
        $this->performIndexRefresh = $performIndexRefresh;

        return $this;
    }

    abstract protected function getIndexName(ElementInterface $element): string;

    /**
     * @throws JsonException
     * @throws ExceptionInterface
     */
    protected function getIndexData(ElementInterface $element): array
    {
        $indexData = $this->elementNormalizer->normalize($element);

        $systemFields = $indexData[FieldCategory::SYSTEM_FIELDS->value];
        $standardFields = $indexData[FieldCategory::STANDARD_FIELDS->value];
        $customFields = [];

        //dispatch event before building checksum
        //$updateIndexDataEvent = new UpdateIndexDataEvent($dataObject, $customFields);
        //$this->eventDispatcher->dispatch($updateIndexDataEvent);
        //$customFields = $updateIndexDataEvent->getCustomFields();

        $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields], JSON_THROW_ON_ERROR));
        $systemFields[SystemField::CHECKSUM->value] = $checksum;

        return [
            FieldCategory::SYSTEM_FIELDS->value => $systemFields,
            FieldCategory::STANDARD_FIELDS->value => $standardFields,
            FieldCategory::CUSTOM_FIELDS->value => $customFields,
        ];
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    public function doUpdateIndexData(ElementInterface $element): AbstractIndexService
    {

        $index = $this->getIndexName($element);

        try {
            $indexDocument = $this->openSearchService->getDocument($index, $element->getId());
            $originalChecksum = $indexDocument['_source'][FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] ?? -1;
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $originalChecksum = -1;
        }

        $indexData = $this->getIndexData($element);

        if ($indexData[FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value] !== $originalChecksum) {

            $this->bulkOperationService->add(['update' => ['_index' => $index, '_id' => $element->getId()]]);
            $this->bulkOperationService->add(['doc' => $indexData, 'doc_as_upsert' => true]);

            $this->logger->info('Add update of element ID ' . $element->getId() . ' from ' . $index . ' index to bulk.');
        } else {
            $this->logger->info('Not updating index ' . $index . ' for element ID ' . $element->getId() . ' - nothing has changed.');
        }

        return $this;
    }

    public function doDeleteFromIndex(int $elementId, string $elementIndexName): AbstractIndexService
    {
        $this->bulkOperationService->add([
            'delete' => [
                '_index' => $this->searchIndexConfigService->getIndexName($elementIndexName),
                '_id' => $elementId,
            ],
        ]);

        $this->logger->info('Add deletion of item ID ' . $elementId . ' from ' . $elementIndexName . ' index to bulk.');

        return $this;
    }

    public function getRelatedItemsOnUpdateQuery(ElementInterface $element, string $operation, int $operationTime, bool $includeElement = false): ?QueryBuilder
    {
        return null;
    }
}
