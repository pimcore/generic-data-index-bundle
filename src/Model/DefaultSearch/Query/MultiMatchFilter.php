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

final class MultiMatchFilter extends BoolQuery implements AsSubQueryInterface
{
    /**
     * @param string[] $fields
     */
    public function __construct(
        private readonly string $term,
        private readonly array $fields = [],
        private readonly string $type = 'best_fields',
        private readonly string $operator = 'or',
    ) {
        $multiMatchParams = [
            'query' => $this->term,
            'type' => $this->type,
            'operator' => $this->operator,
        ];

        if (!empty($this->fields)) {
            $multiMatchParams['fields'] = $this->fields;
        }

        parent::__construct([
            ConditionType::FILTER->value => [
                'multi_match' => $multiMatchParams,
            ],
        ]);
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getMatchType(): string
    {
        return $this->type;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function toArrayAsSubQuery(): array
    {
        $multiMatchParams = [
            'query' => $this->term,
            'type' => $this->type,
            'operator' => $this->operator,
        ];

        if (!empty($this->fields)) {
            $multiMatchParams['fields'] = $this->fields;
        }

        return [
            'multi_match' => $multiMatchParams,
        ];
    }
}

