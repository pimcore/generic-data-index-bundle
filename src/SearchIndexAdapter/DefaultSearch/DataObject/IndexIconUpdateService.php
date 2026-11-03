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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\UpdateByQueryServiceInterface;

/**
 * @internal
 */
final readonly class IndexIconUpdateService implements IndexIconUpdateServiceInterface
{
    public function __construct(private UpdateByQueryServiceInterface $updateByQueryService)
    {
    }

    public function updateIcon(string $indexName, ?string $icon): void
    {
        $query = $icon === null ? $this->getQueryForNullIcon() : $this->getQueryForIconString($icon);

        $params = [
            'index' => $indexName,
            'refresh' => true,
            'body' => [
                'script' => [
                    'source' => 'ctx._source.system_fields.classDefinitionIcon = params.icon',
                    'lang' => 'painless',
                    'params' => [
                        'icon' => $icon,
                    ],
                ],
                'query' => $query,
            ],
        ];

        $this->updateByQueryService->updateByQuery(
            $params,
            static function (array $source) use ($icon): array {
                $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::CLASS_DEFINITION_ICON->value] = $icon;

                return $source;
            }
        );
    }

    private function getQueryForNullIcon(): array
    {
        return [
            'bool' => [
                'filter' => [
                    'exists' => ['field' => SystemField::CLASS_DEFINITION_ICON->getPath()],
                ],
            ],
        ];
    }

    private function getQueryForIconString(string $icon): array
    {
        return [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must_not' => [
                                'exists' => ['field' => SystemField::CLASS_DEFINITION_ICON->getPath()],
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                'term' => [SystemField::CLASS_DEFINITION_ICON->getPath() => $icon],
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
