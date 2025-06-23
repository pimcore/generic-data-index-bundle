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

final class TermFilter extends BoolQuery implements AsSubQueryInterface
{
    public function __construct(
        private readonly string $field,
        private readonly string|int|bool $term,
    ) {
        parent::__construct([
            ConditionType::FILTER->value => [
                'term' => [
                    $this->field => $this->term,
                ],
            ],
        ]);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getTerm(): string|int|bool
    {
        return $this->term;
    }

    public function toArrayAsSubQuery(): array
    {
        return [
            'term' => [
                $this->field => $this->term,
            ],
        ];
    }
}
