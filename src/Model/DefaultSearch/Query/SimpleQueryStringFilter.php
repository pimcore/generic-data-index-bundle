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

final class SimpleQueryStringFilter extends BoolQuery implements AsSubQueryInterface
{
    public function __construct(
        private readonly string $term
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
                'simple_query_string' => [
                    'query' => $this->term,
                ],
            ],
        ]);
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function toArrayAsSubQuery(): array
    {
        return [
            'simple_query_string' => [
                'query' => $this->term,
            ],
        ];
    }
}
