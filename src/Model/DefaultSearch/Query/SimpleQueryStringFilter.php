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
    /**
     * @param string[] $fields
     */
    public function __construct(
        private readonly string $term,
        private readonly string $defaultOperator = 'AND',
        private readonly array $fields = [],
        private readonly ?string $flags = 'PHRASE|WHITESPACE',
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
                'simple_query_string' => $this->buildSimpleQueryString(),
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
            'simple_query_string' => $this->buildSimpleQueryString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSimpleQueryString(): array
    {
        $simpleQueryString = [
            'query' => $this->term,
            'default_operator' => $this->defaultOperator,
        ];

        if ($this->fields !== []) {
            $simpleQueryString['fields'] = $this->fields;
        }

        if ($this->flags !== null) {
            $simpleQueryString['flags'] = $this->flags;
        }

        return $simpleQueryString;
    }
}
