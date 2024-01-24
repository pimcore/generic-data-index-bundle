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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use Exception;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\ElementTypeAdapterService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;

class PathService
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly Client $openSearchClient,
        protected readonly ElementTypeAdapterService $typeAdapterService,
        protected readonly SearchIndexConfigService $searchIndexConfigService,
    ) {
    }

    /**
     * Directly update children paths in OpenSearch for assets as otherwise you might get strange results if you rename a folder in the portal engine frontend.
     *
     * @throws Exception
     */
    public function rewriteChildrenIndexPaths(ElementInterface $element): void
    {
        $oldFullPath = $this->getCurrentIndexFullPath($element);

        if (empty($oldFullPath) || $oldFullPath === $element->getRealFullPath()) {
            return;
        }

        $typeAdapter = $this->typeAdapterService->getTypeAdapter($element);

        if (!$typeAdapter->childrenPathRewriteNeeded($element)) {
            return;
        }

        $indexName = $typeAdapter->getAliasIndexNameByElement($element);

        $countResult = $this->countDocumentsByPath($indexName, $oldFullPath);

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

        $this->updatePath($indexName, $oldFullPath, $element->getRealFullPath());
    }

    private function updatePath(string $indexName, string $currentPath, string $newPath): void
    {
        $pathLevels = explode('/', $newPath);

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
                        'currentPath' => $currentPath . '/',
                        'newPath' => $newPath . '/',
                        'changePathLevel' => count($pathLevels) - 1,
                        'newPathLevelName' => end($pathLevels),
                    ],
                ],

                'query' => [
                    'term' => [
                        FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::FULL_PATH->value
                        => $currentPath,
                    ],
                ],
            ],
        ];

        $this->openSearchClient->updateByQuery($query);
    }

    private function countDocumentsByPath(string $indexName, string $path): int
    {
        $this->openSearchClient->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => [
                    'term' => [
                        FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::FULL_PATH->value => $path,
                    ],
                ],
                'size' => 0,
            ],
        ]);

        return $countResult['hits']['total'] ?? 0;
    }

    public function getCurrentIndexFullPath(ElementInterface $element): ?string
    {
        $indexName = $this->typeAdapterService
            ->getTypeAdapter($element)
            ->getAliasIndexNameByElement($element);

        $result = $this->openSearchClient->search(
            [
                'index' => $indexName,
                'body' => [
                    '_source' => [FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::FULL_PATH->value],
                    'query' => [
                        'term' => [
                            FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::ID->value =>
                                $element->getId(),
                        ],
                    ],
                ],
            ]
        );

        return $result['hits']['hits'][0]['_source']['system_fields']['fullPath'] ?? null;
    }
}
