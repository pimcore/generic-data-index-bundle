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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Throwable;

/**
 * @internal
 */
final readonly class MappedFieldPathService implements MappedFieldPathServiceInterface
{
    private const PROPERTIES = 'properties';

    private const MAPPINGS = 'mappings';

    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private CachedSearchIndexMappingServiceInterface $searchIndexMappingService,
    ) {
    }

    public function isUndeclaredInMapping(SearchInterface $search, string $fieldPath): bool
    {
        try {
            $indexMapping = $this->searchIndexMappingService->getMapping(
                $this->indexNameResolver->resolveIndexName($search)
            );
        } catch (Throwable) {
            // the index could not be read, which says nothing about the path
            return false;
        }

        if ($indexMapping === []) {
            return false;
        }

        foreach ($indexMapping as $mapping) {
            if (!is_array($mapping)) {
                return false;
            }

            if ($this->declaresPath($fieldPath, $mapping[self::MAPPINGS] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The mapping nests one "properties" level per path segment, which is what the index mapping of a
     * nested field declares and what a dotted field path addresses.
     */
    private function declaresPath(string $fieldPath, array $mapping): bool
    {
        foreach (explode('.', $fieldPath) as $segment) {
            $mapping = $mapping[self::PROPERTIES][$segment] ?? null;
            if (!is_array($mapping)) {
                return false;
            }
        }

        return true;
    }
}
