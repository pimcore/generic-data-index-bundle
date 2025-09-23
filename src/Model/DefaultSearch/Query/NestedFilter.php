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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;

final class NestedFilter extends BoolQuery implements AsSubQueryInterface
{
    public function __construct(
        private readonly string $path,
        private readonly array $subQuery,
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
                'nested' => [
                    'path' => $this->path,
                    'query' => $this->subQuery,
                ],
            ],
        ]);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function toArrayAsSubQuery(): array
    {
        return [
            'nested' => [
                'path' => $this->path,
                'query' => $this->subQuery,
            ],
        ];
    }
}
