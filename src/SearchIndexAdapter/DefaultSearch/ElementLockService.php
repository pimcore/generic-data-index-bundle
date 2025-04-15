<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
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

/**
 * @internal
 */
final class ElementLockService implements ElementLockServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly AdapterServiceInterface $typeAdapterService,
        private readonly PathServiceInterface $pathService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
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

        if ($this->countLockedChildren($fullPath, $indexName) > 0) {
            return true;
        }

        if ($this->countLockedPropagateParents($fullPath, $indexName) > 0) {
            return true;
        }

        return false;
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
        $result = $this->client->search( [
            'index' => $indexName,
            'body' => $search->toArray()
        ]);

        return $result['hits']['total']['value'];
    }

    private function addLockedChildrenQuery(DefaultSearchInterface $search, string $path): void
    {
        $search->addQuery(
            new BoolQuery([
                ConditionType::MUST->value => [
                    [
                        ConditionType::WILDCARD->value => [
                            SystemField::FULL_PATH->getPath(AttributeType::KEYWORD->value) => [
                                'value' => $path . '/*'
                            ]
                        ]
                    ],
                    [
                        ConditionType::EXISTS->value => [
                            'field' => SystemField::LOCKED->getPath(AttributeType::KEYWORD->value)
                        ]
                    ]
                ],
                ConditionType::MUST_NOT->value => [
                    new TermFilter(
                        SystemField::LOCKED->getPath(AttributeType::KEYWORD->value),
                        ''
                    )
                ]
            ])
        );
    }

    private function addLockedPropagateParentsQuery(DefaultSearchInterface $search, string $path): void
    {
        $search->addQuery(new TermsFilter(
            SystemField::FULL_PATH->getPath('keyword'),
            $this->pathService->getAllParentPaths([$path])
        ));
        $search->addQuery(new TermFilter(
            SystemField::LOCKED->getPath('keyword'),
            'propagate'
        ));
    }

    private function getIndexNameByType(string $type): string
    {
        $defaultIndexNames = [
            IndexName::ASSET->value,
            IndexName::DATA_OBJECT->value,
            IndexName::DOCUMENT->value,
        ];

         $indexName = in_array($type, $defaultIndexNames, true) ? $type : IndexName::ELEMENT_SEARCH->value;

         return $this->searchIndexConfigService->getIndexName($indexName);
    }
}
