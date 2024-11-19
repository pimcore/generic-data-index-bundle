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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final readonly class IndexIconUpdateService implements IndexIconUpdateServiceInterface
{
    public function __construct(private SearchClientInterface $client)
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
        $this->client->updateByQuery($params);
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
