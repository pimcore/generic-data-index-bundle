<?php

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

use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\Tag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractIndexService implements IndexServiceInterface
{
    use LoggerAwareTrait;

    protected array $coreFieldsConfig = [];

    protected bool $performIndexRefresh = false;

    protected Client $openSearchClient;

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected SearchIndexConfigService $searchIndexConfigService,
        protected LanguageService $languageService,
        protected WorkflowService $workflowService,
        protected OpenSearchService $openSearchService,
        protected BulkOperationService $bulkOperationService,
    ) {
        $this->openSearchClient = $this->openSearchService->getOpenSearchClient();
    }

    public function getCurrentIndexFullPath(ElementInterface $element, string $indexName): ?string
    {
        $result = $this->openSearchClient->search(
            [
                'index' => $indexName,
                'body' => [
                    '_source' => [FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::FULL_PATH->value],
                    'query' => [
                        'term' => [
                            FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::ID->value =>
                                $element->getId(),
                        ],
                    ],
                ],
            ]
        );

        return $result['hits']['hits'][0]['_source']['system_fields']['fullPath'] ?? null;
    }

    public function rewriteChildrenIndexPaths(ElementInterface $element, string $indexName, string $oldFullPath)
    {
        $pathLevels = explode('/', $element->getRealFullPath());

        $countResult = $this->openSearchClient->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => [
                    'term' => [
                        FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::FULL_PATH->value
                        => $oldFullPath,
                    ],
                ],
                'size' => 0,
            ],
        ]);

        $countResult = $countResult['hits']['total'] ?? 0;

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

        $query = [
            'index' => $indexName,
            'refresh' => true,
            'conflicts' => 'proceed',
            'body' => [

                'script' => [
                    'lang' => 'painless',
                    'source' => '
                        String currentPath = "";
                            if(ctx._source.system_fields.path.length() >= params.currentPath.length()) {
                               currentPath = ctx._source.system_fields.path.substring(0,params.currentPath.length());
                            }
                            if(currentPath == params.currentPath) {
                                String subPath = ctx._source.system_fields.path.substring(params.currentPath.length());
                                ctx._source.system_fields.path = params.newPath + subPath;

                                String subFullPath = ctx._source.system_fields.fullPath.substring(params.currentPath.length());
                                ctx._source.system_fields.fullPath = params.newPath + subFullPath;

                                for (int i = 0; i < ctx._source.system_fields.pathLevels.length; i++) {


                                  if(ctx._source.system_fields.pathLevels[i].level == params.changePathLevel) {

                                    ctx._source.system_fields.pathLevels[i].name = params.newPathLevelName;
                                  }
                                }
                            }
                            ctx._source.system_fields.checksum = 0
                   ',

                    'params' => [
                        'currentPath' => $oldFullPath . '/',
                        'newPath' => $element->getRealFullPath() . '/',
                        'changePathLevel' => count($pathLevels) - 1,
                        'newPathLevelName' => end($pathLevels),
                    ],
                ],

                'query' => [
                    'term' => [
                        FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::FULL_PATH->value
                        => $oldFullPath,
                    ],
                ],
            ],
        ];

        $this->openSearchClient->updateByQuery($query);
    }

    abstract public function setCoreFieldsConfig(array $coreFieldsConfig);

    /**
     * @param string|null $fieldName
     *
     * @return array
     */
    public function getCoreFieldsConfig($fieldName = null)
    {
        if ($fieldName !== null && array_key_exists($fieldName, $this->coreFieldsConfig)) {
            return $this->coreFieldsConfig[$fieldName];
        }

        return $this->coreFieldsConfig;
    }

    /**
     * @return array
     */
    protected function extractSystemFieldsMapping()
    {
        $mappingProperties = [];

        $mappingProperties[FieldCategory::SYSTEM_FIELDS->value]['properties'] = array_map(
            function ($fieldProperties) {
                $mapping = [
                    'type' => $fieldProperties['type'],
                ];
                if (!empty($fieldProperties['analyzer'])) {
                    $mapping['analyzer'] = $fieldProperties['analyzer'];
                }
                if (!empty($fieldProperties['properties'])) {
                    $mapping['properties'] = $fieldProperties['properties'];
                }
                if (!empty($fieldProperties['fields'])) {
                    $mapping['fields'] = $fieldProperties['fields'];
                }

                return $mapping;
            },
            $this->getCoreFieldsConfig()
        );

        return $mappingProperties;
    }

    protected function extractPathLevels(ElementInterface $element): array
    {
        $path = $element->getType() === 'folder' ? $element->getRealFullPath() : $element->getPath();
        $levels = explode('/', rtrim($path, '/'));
        unset($levels[0]);

        $result = [];
        foreach ($levels as $level => $name) {
            $result[] = [
                'level' => $level,
                'name' => $name,
            ];
        }

        return $result;
    }

    /**
     * @param ElementInterface $element
     *
     * @return array
     */
    protected function extractTagIds(ElementInterface $element): array
    {
        $tag = new Tag();
        $tags = $tag->getDao()->getTagsForElement(Service::getElementType($element), $element->getId());

        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $tag->getId();
        }

        return $ids;
    }

    /**
     * @return bool
     */
    public function isPerformIndexRefresh(): bool
    {
        return $this->performIndexRefresh;
    }

    /**
     * @param bool $performIndexRefresh
     *
     * @return $this
     */
    public function setPerformIndexRefresh(bool $performIndexRefresh)
    {
        $this->performIndexRefresh = $performIndexRefresh;

        return $this;
    }

    abstract protected function getIndexName(ElementInterface $element): string;

    abstract protected function getIndexData(ElementInterface $element): array;

    public function doUpdateIndexData(ElementInterface $element): self
    {

        $index = $this->getIndexName($element);

        $params = [
            'index' => $index,
            'id' => $element->getId(),
        ];

        try {
            $indexDocument = $this->openSearchClient->get($params);
            $originalChecksum = $indexDocument['_source'][FieldCategory::SYSTEM_FIELDS->value][FieldCategory\SystemField::CHECKSUM->value] ?? -1;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            $originalChecksum = -1;
        }

        $indexData = $this->getIndexData($element);

        if ($indexData[FieldCategory::SYSTEM_FIELDS->value][FieldCategory\SystemField::CHECKSUM->value] !== $originalChecksum) {

            $this->bulkOperationService->add(['update' => ['_index' => $index, '_id' => $element->getId()]]);
            $this->bulkOperationService->add(['doc' => $indexData, 'doc_as_upsert' => true]);

            $this->logger->info('Add update of element ID ' . $element->getId() . ' from ' . $index . ' index to bulk.');
        } else {
            $this->logger->info('Not updating index ' . $index . ' for element ID ' . $element->getId() . ' - nothing has changed.');
        }

        return $this;
    }

    public function doDeleteFromIndex(int $elementId, string $elementIndexName): self
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
}
