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

use function array_keys;
use function in_array;
use function is_string;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\QueryType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\ElementLockServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\SearchClient\SearchClientInterface;
use function str_starts_with;

/**
 * @internal
 */
final class ElementLockService implements ElementLockServiceInterface
{
    use LoggerAwareTrait;

    /**
     * Above this number of locked elements in an index, lock checks query the index per element
     * instead of keeping all locked elements in memory.
     */
    private const int MAX_CACHED_LOCKS = 1000;

    private const string LOCK_PROPAGATE = 'propagate';

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly PathServiceInterface $pathService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly LockedElementsCacheInterface $lockedElementsCache,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function unlockPropagate(ElementInterface $element): void
    {
        $typeAdapter = $this->typeAdapterService->getTypeAdapter($element);
        $indexName = $typeAdapter->getAliasIndexNameByElement($element);

        $query = [
            'index' => $indexName,
            'refresh' => true,
            'conflicts' => 'proceed',
            'body' => [
                'script' => [
                    'source' => 'ctx._source.system_fields.locked = null',
                    'lang' => 'painless',
                ],
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'term' => [
                                    SystemField::FULL_PATH->getPath(AttributeType::KEYWORD->value) =>
                                        $element->getRealFullPath(),
                                ],
                            ],
                            [
                                'prefix' => [
                                    SystemField::FULL_PATH->getPath(AttributeType::KEYWORD->value) =>
                                        $element->getRealFullPath() . '/',
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
        ];

        $this->client->updateByQuery($query);
        $this->lockedElementsCache->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function isElementLocked(string $fullPath, string $type, ?string $locked = null): bool
    {
        if ($fullPath === '/') {
            return false;
        }

        if ($locked !== null && $locked !== '') {
            return true;
        }

        return $this->isLockedByIndexData($fullPath, $type);
    }

    private function isLockedByIndexData(string $fullPath, string $type): bool
    {
        $indexName = $this->getIndexNameByType($type);

        // Lists such as grids and trees check every result row. Load the locked elements of the index
        // once instead of running two queries per row; usually only a handful of elements are locked.
        $locks = $this->getLockedElements($indexName);
        if ($locks !== null) {
            return $this->hasLockedChild($fullPath, $locks) || $this->hasPropagatingLockedParent($fullPath, $locks);
        }

        if ($this->countLockedChildren($fullPath, $indexName) > 0) {
            return true;
        }

        if ($this->countLockedPropagateParents($fullPath, $indexName) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string>|null lock value by full path, null if there are too many locks
     */
    private function getLockedElements(string $indexName): ?array
    {
        if (!$this->lockedElementsCache->isLoaded($indexName)) {
            $this->lockedElementsCache->setLocks($indexName, $this->loadLockedElements($indexName));
        }

        return $this->lockedElementsCache->getLocks($indexName);
    }

    /**
     * @return array<string, string>|null
     */
    private function loadLockedElements(string $indexName): ?array
    {
        $search = new Search(
            size: self::MAX_CACHED_LOCKS,
            source: [SystemField::FULL_PATH->getPath(), SystemField::LOCKED->getPath()]
        );
        $search->addQuery($this->createLockedQuery());

        $result = $this->client->search([
            'index' => $indexName,
            'body' => $search->toArray(),
        ]);

        if ($result['hits']['total']['value'] > self::MAX_CACHED_LOCKS) {
            return null;
        }

        $locks = [];
        foreach ($result['hits']['hits'] as $hit) {
            $source = $hit['_source'] ?? [];
            $path = SystemField::FULL_PATH->getData($source);
            $locked = SystemField::LOCKED->getData($source);
            if (is_string($path) && is_string($locked) && $locked !== '') {
                $locks[$path] = $locked;
            }
        }

        return $locks;
    }

    /**
     * Same rule as addLockedChildrenQuery(): any descendant with a lock.
     *
     * @param array<string, string> $locks
     */
    private function hasLockedChild(string $fullPath, array $locks): bool
    {
        $prefix = $fullPath . '/';
        foreach (array_keys($locks) as $lockedPath) {
            if (str_starts_with($lockedPath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Same rule as addLockedPropagateParentsQuery(): an ancestor locked with "propagate".
     *
     * @param array<string, string> $locks
     */
    private function hasPropagatingLockedParent(string $fullPath, array $locks): bool
    {
        foreach ($this->pathService->getAllParentPaths([$fullPath]) as $parentPath) {
            if (($locks[$parentPath] ?? null) === self::LOCK_PROPAGATE) {
                return true;
            }
        }

        return false;
    }

    private function createLockedQuery(): BoolQuery
    {
        return new BoolQuery([
            ConditionType::MUST->value => [
                [
                    ConditionType::EXISTS->value => [
                        'field' => SystemField::LOCKED->getPath(AttributeType::KEYWORD->value),
                    ],
                ],
            ],
            ConditionType::MUST_NOT->value => [
                new TermFilter(
                    SystemField::LOCKED->getPath(AttributeType::KEYWORD->value),
                    ''
                ),
            ],
        ]);
    }

    private function countLockedChildren(string $fullPath, string $indexName): int
    {
        $search = new Search();
        $this->addLockedChildrenQuery($search, $fullPath);

        return $this->getResultCount($search, $indexName);
    }

    private function countLockedPropagateParents(string $fullPath, string $indexName): int
    {
        $search = new Search();
        $this->addLockedPropagateParentsQuery($search, $fullPath);

        return $this->getResultCount($search, $indexName);
    }

    private function getResultCount(DefaultSearchInterface $search, string $indexName): int
    {
        $result = $this->client->search([
            'index' => $indexName,
            'body' => $search->toArray(),
        ]);

        return $result['hits']['total']['value'];
    }

    private function addLockedChildrenQuery(DefaultSearchInterface $search, string $fullPath): void
    {
        $search->addQuery(
            new BoolQuery([
                ConditionType::MUST->value => [
                    [
                        QueryType::WILDCARD->value => [
                            SystemField::FULL_PATH->getPath(AttributeType::KEYWORD->value) => [
                                'value' => $fullPath . '/*',
                            ],
                        ],
                    ],
                    [
                        ConditionType::EXISTS->value => [
                            'field' => SystemField::LOCKED->getPath(AttributeType::KEYWORD->value),
                        ],
                    ],
                ],
                ConditionType::MUST_NOT->value => [
                    new TermFilter(
                        SystemField::LOCKED->getPath(AttributeType::KEYWORD->value),
                        ''
                    ),
                ],
            ])
        );
    }

    private function addLockedPropagateParentsQuery(DefaultSearchInterface $search, string $fullPath): void
    {
        $search->addQuery(new TermsFilter(
            SystemField::FULL_PATH->getPath('keyword'),
            $this->pathService->getAllParentPaths([$fullPath])
        ));
        $search->addQuery(new TermFilter(
            SystemField::LOCKED->getPath('keyword'),
            self::LOCK_PROPAGATE
        ));
    }

    private function getIndexNameByType(string $type): string
    {
        $defaultIndexNames = [
            IndexName::ASSET->value,
            IndexName::DATA_OBJECT->value,
            IndexName::DOCUMENT->value,
        ];

        $type = $this->getLongType($type);
        $indexName = in_array($type, $defaultIndexNames, true) ? $type : IndexName::ELEMENT_SEARCH->value;

        return $this->searchIndexConfigService->getIndexName($indexName);
    }

    private function getLongType(string $type): string
    {
        if ($type === 'object' || $type === 'dataObject') {
            return IndexName::DATA_OBJECT->value;
        }

        return $type;
    }
}
