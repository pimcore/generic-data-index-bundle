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
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class PathService implements PathServiceInterface
{
    use LoggerAwareTrait;

    /**
     * @param array<string, string|null> $frontendPrefixes pimcore.assets.frontend_prefixes
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly array $frontendPrefixes = [],
    ) {
    }

    /**
     * Directly update children paths in the search index when an element with children
     * was moved or renamed, as the children are not re-indexed individually.
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

        $indexName = $typeAdapter->getPathRewriteIndexName($element);

        $countResult = $this->countDocumentsByPath($indexName, $oldFullPath);

        if ($countResult === 0) {
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
                        'thumbnailPrefixes' => $this->getThumbnailPathPrefixes(),
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

    /**
     * rewriteThumbnailPath() rewrites the moved path inside a thumbnail URL. The URL can start with a
     * cache buster and with one of the configured frontend prefixes, followed by the plain or the
     * urlencoded path (frontend URLs are built via urlencode_ignore_slash()).
     */
    private function getScriptSource(): string
    {
        return 'String rewriteThumbnailPath(String thumbnail, Map p) {
                    String cacheBuster = "";
                    String rest = thumbnail;
                    if(rest.startsWith("/cache-buster-")) {
                        int slashPos = rest.indexOf("/", 1);
                        if(slashPos > 0) {
                            cacheBuster = rest.substring(0, slashPos);
                            rest = rest.substring(slashPos);
                        }
                    }

                    for(def prefix : p.thumbnailPrefixes) {
                        if(!rest.startsWith(prefix)) {
                            continue;
                        }
                        String path = rest.substring(prefix.length());
                        if(path.startsWith(p.currentPathEncoded)) {
                            return cacheBuster + prefix + p.newPathEncoded
                                + path.substring(p.currentPathEncoded.length());
                        }
                        if(path.startsWith(p.currentPath)) {
                            return cacheBuster + prefix + p.newPath + path.substring(p.currentPath.length());
                        }
                    }

                    return thumbnail;
                }

                String currentPath = "";
                if(ctx._source.system_fields.path.length() >= params.currentPath.length()) {
                   currentPath = ctx._source.system_fields.path.substring(0,params.currentPath.length());
                }
                if(currentPath == params.currentPath) {
                    String subPath = ctx._source.system_fields.path.substring(params.currentPath.length());
                    ctx._source.system_fields.path = params.newPath + subPath;

                    String subFullPath = ctx._source.system_fields.fullPath.substring(params.currentPath.length());
                    ctx._source.system_fields.fullPath = params.newPath + subFullPath;

                    if(ctx._source.system_fields.thumbnail instanceof String) {
                        ctx._source.system_fields.thumbnail = rewriteThumbnailPath(
                            ctx._source.system_fields.thumbnail,
                            params
                        );
                    }

                    if(ctx._source.containsKey("custom_fields") && 
                       ctx._source.custom_fields instanceof Map &&
                       ctx._source.custom_fields.containsKey("PortalEngineBundle") && 
                       ctx._source.custom_fields.PortalEngineBundle instanceof Map &&
                       ctx._source.custom_fields.PortalEngineBundle.containsKey("system_fields") && 
                       ctx._source.custom_fields.PortalEngineBundle.system_fields instanceof Map &&
                       ctx._source.custom_fields.PortalEngineBundle.system_fields.containsKey("thumbnail")) {
                        def customFields = ctx._source.custom_fields.PortalEngineBundle.system_fields;
                        if(customFields.thumbnail instanceof String) {
                            customFields.thumbnail = rewriteThumbnailPath(customFields.thumbnail, params);
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
     * Prefixes a thumbnail URL can start with: the configured frontend prefixes, longest first so that a
     * prefix is never cut short by a shorter one, and the empty prefix for plain paths last.
     *
     * @return list<string>
     */
    private function getThumbnailPathPrefixes(): array
    {
        $prefixes = array_values(array_unique(array_filter(
            $this->frontendPrefixes,
            static fn (?string $prefix): bool => $prefix !== null && $prefix !== ''
        )));
        usort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $prefixes[] = '';

        return $prefixes;
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
