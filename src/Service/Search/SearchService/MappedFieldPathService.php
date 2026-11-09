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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\MappedFieldPathDeclaration;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;

/**
 * @internal
 */
final readonly class MappedFieldPathService implements MappedFieldPathServiceInterface
{
    private const PROPERTIES = 'properties';

    private const FIELDS = 'fields';

    private const MAPPINGS = 'mappings';

    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private CachedSearchIndexMappingServiceInterface $searchIndexMappingService,
        private SearchPqlFieldNameTransformationServiceInterface $fieldNameTransformationService,
    ) {
    }

    /**
     * The mapping response is keyed by concrete index name even when an alias was asked for, which is
     * what makes the per index answer available at all - and what makes the names usable as query
     * terms, since `_index` of a document is that same concrete name.
     *
     * The path is resolved the same way the query that will read it is resolved, which is what
     * $pqlFieldNameResolutionEnabled carries over from the filter. A caller's path is the one the
     * filter was written with, and field name resolution is what the query is actually built on: it
     * appends a sub-field when the mapping has one - `.keyword`, `.sort`, `.id` for a relation - and it
     * takes that decision over all the indices behind the alias at once, so a sub-field only one of
     * them declares still moves the query onto it for all of them. Answering about the path as given
     * would then answer about a path the query never reads, and the per index answer below would report
     * an index that declares the bare path without the sub-field as declaring the address.
     *
     * Only expected failures of reading the mapping are swallowed: the index name cannot be resolved,
     * the index is gone, the cluster did not answer. A programming error is not a mapping that could
     * not be read and is left to propagate, because the caller's fallback for "no answer" is to build
     * the query it would have built anyway - which is the behaviour the guard exists to prevent.
     */
    public function resolveDeclaration(
        SearchInterface $search,
        string $fieldPath,
        bool $pqlFieldNameResolutionEnabled = false,
    ): MappedFieldPathDeclaration {
        try {
            $indexMapping = $this->searchIndexMappingService->getMapping(
                $this->indexNameResolver->resolveIndexName($search)
            );

            if ($pqlFieldNameResolutionEnabled) {
                $fieldPath = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldPath);
            }
        } catch (Exception) {
            // the mapping could not be read, which says nothing about the path
            return MappedFieldPathDeclaration::unanswered();
        }

        $declaringIndices = [];
        $omittingIndices = [];

        foreach ($indexMapping as $indexName => $mapping) {
            if (!is_array($mapping)) {
                // not the response shape the per index answer is read from, so nothing is asserted
                return MappedFieldPathDeclaration::unanswered();
            }

            // a numeric index name arrives as an int array key, and `_index` compares it as a string
            if ($this->declaresPath($fieldPath, $mapping[self::MAPPINGS] ?? [])) {
                $declaringIndices[] = (string) $indexName;
            } else {
                $omittingIndices[] = (string) $indexName;
            }
        }

        // an empty response leaves both lists empty, which is the unanswered case
        return MappedFieldPathDeclaration::fromIndices($declaringIndices, $omittingIndices);
    }

    /**
     * The mapping nests one container level per path segment, which is what the index mapping of a
     * nested field declares and what a dotted field path addresses.
     *
     * There are two such containers and a path segment may be resolved by either. An object or nested
     * property is listed under "properties"; a multi-field is listed under "fields" of the field it
     * belongs to, which is how this bundle declares every text key - `getMappingForTextKeyword()`
     * gives a key both a `keyword` and a `sort` multi-field, and a filter may address those. Reading
     * "properties" alone would report a mapped multi-field as undeclared. The same two containers are
     * traversed by the adapter's own MappingAnalyzerService::fieldPathExistsInMapping().
     */
    private function declaresPath(string $fieldPath, array $mapping): bool
    {
        foreach (explode('.', $fieldPath) as $segment) {
            $mapping = $mapping[self::PROPERTIES][$segment] ?? $mapping[self::FIELDS][$segment] ?? null;
            if (!is_array($mapping)) {
                return false;
            }
        }

        return true;
    }
}
