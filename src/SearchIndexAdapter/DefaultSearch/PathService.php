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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class PathService implements PathServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    /**
     * Directly update children paths in OpenSearch for assets as otherwise you might get strange results
     * if you rename a folder in the portal engine frontend.
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
                'Direct rewrite of children paths in OpenSearch was skipped as more than %s
                items need an update (%s items).
                The index will be updated asynchronously via index update queue command cronjob.',
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

    public function getCurrentIndexFullPath(ElementInterface $element): ?string
    {
        $indexName = $this->typeAdapterService
            ->getTypeAdapter($element)
            ->getAliasIndexNameByElement($element);

        $result = $this->client->search(
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
                    'source' => $this->getScriptSource(),

                    'params' => [
                        'currentPath' => $currentPath . '/',
                        'newPath' => $newPath . '/',
                        // Portal Engine's frontend thumbnail URLs are built via
                        // urlencode_ignore_slash() (rawurlencode() per path segment), so accented
                        // and other non-unreserved characters end up percent-encoded there. Provide
                        // the equivalently-encoded path so the script can match/replace that form too.
                        'currentPathEncoded' => $this->encodePathSegments($currentPath) . '/',
                        'newPathEncoded' => $this->encodePathSegments($newPath) . '/',
                        'now' => date('c'),
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

        $this->client->updateByQuery($query);
    }

    private function getScriptSource(): string
    {
        return 'String currentPath = "";
                if(ctx._source.system_fields.path.length() >= params.currentPath.length()) {
                   currentPath = ctx._source.system_fields.path.substring(0,params.currentPath.length());
                }
                if(currentPath == params.currentPath) {
                    String subPath = ctx._source.system_fields.path.substring(params.currentPath.length());
                    ctx._source.system_fields.path = params.newPath + subPath;

                    String subFullPath = ctx._source.system_fields.fullPath.substring(params.currentPath.length());
                    ctx._source.system_fields.fullPath = params.newPath + subFullPath;

                    if(ctx._source.system_fields.thumbnail != null && 
                       ctx._source.system_fields.thumbnail.length() >= params.currentPath.length()) {
                        String thumbnailPrefix = ctx._source.system_fields.thumbnail.substring(
                            0, 
                            params.currentPath.length()
                        );
                        if(thumbnailPrefix == params.currentPath) {
                            String thumbnailSubPath = ctx._source.system_fields.thumbnail.substring(
                                params.currentPath.length()
                            );
                            ctx._source.system_fields.thumbnail = params.newPath + thumbnailSubPath;
                        }   
                    }

                    if(ctx._source.containsKey("custom_fields") && 
                       ctx._source.custom_fields instanceof Map &&
                       ctx._source.custom_fields.containsKey("PortalEngineBundle") && 
                       ctx._source.custom_fields.PortalEngineBundle instanceof Map &&
                       ctx._source.custom_fields.PortalEngineBundle.containsKey("system_fields") && 
                       ctx._source.custom_fields.PortalEngineBundle.system_fields instanceof Map &&
                       ctx._source.custom_fields.PortalEngineBundle.system_fields.containsKey("thumbnail")) {
                        def customFields = ctx._source.custom_fields.PortalEngineBundle.system_fields;
                        if(customFields.thumbnail != null && customFields.thumbnail instanceof String) {
                            String thumb = customFields.thumbnail;
                            String prefix = "";
                            int pathStart = 0;
                            
                            if(thumb.startsWith("/cache-buster-")) {
                                int slashPos = thumb.indexOf("/", 1);
                                if(slashPos > 0) {
                                    prefix = thumb.substring(0, slashPos);
                                    pathStart = slashPos;
                                }
                            }
                            
                            String thumbPath = thumb.substring(pathStart);

                            if(thumbPath.startsWith(params.currentPathEncoded) || thumbPath.startsWith(params.currentPath)) {
                                boolean matchedEncoded = thumbPath.startsWith(params.currentPathEncoded);
                                String matchedPath = matchedEncoded ? params.currentPathEncoded : params.currentPath;
                                String remainingPath = thumbPath.substring(matchedPath.length());
                                String replacementPath = matchedEncoded ? params.newPathEncoded : params.newPath;
                                customFields.thumbnail = prefix + replacementPath + remainingPath;
                            }

                        }
                    }

                    String[] newPathParts = ctx._source.system_fields.path.splitOnToken("/");
                    
                    def newLevels = [];
                    int levelCounter = 1;
                    for (int i = 0; i < newPathParts.length; i++) {
                      if(newPathParts[i].length() > 0) {
                        newLevels.add(["level": levelCounter, "name": newPathParts[i]]);
                        levelCounter++;
                      }
                    }
                    ctx._source.system_fields.pathLevels = newLevels;
                }
                ctx._source.system_fields.modificationDate = params.now;
                ctx._source.system_fields.checksum = 0';
    }

    /**
     * Mirrors urlencode_ignore_slash() (rawurlencode() applied per path segment), which is how
     * Portal Engine builds its frontend thumbnail URLs (ThumbnailService::getThumbnailPath()).
     */
    private function encodePathSegments(string $path): string
    {
        return \urlencode_ignore_slash($path);
    }

    private function countDocumentsByPath(string $indexName, string $path): int
    {
        $countResult = $this->client->search([
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
}
