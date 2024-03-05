<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;

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
