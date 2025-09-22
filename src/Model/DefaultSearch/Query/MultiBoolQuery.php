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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\QueryType;

final class MultiBoolQuery extends BoolQuery implements AsSubQueryInterface
{
    public function __construct(
        private readonly string $field,
        /** @var (bool)[] */
        private readonly array $terms,
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
                QueryType::BOOL->value => [
                    ConditionType::SHOULD->value => [
                        (new BoolExistsQuery($this->field))->toArrayAsSubQuery(),
                        (new TermsFilter($this->field, $this->terms))->toArrayAsSubQuery(),
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ]);
    }

    public function getField(): string
    {
        return $this->field;
    }

    /** @return (bool)[] */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function toArrayAsSubQuery(): array
    {
        return [
            QueryType::BOOL->value => [
                ConditionType::SHOULD->value => [
                    (new BoolExistsQuery($this->field))->toArray(),
                    (new TermsFilter($this->field, $this->terms))->toArray(),
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
