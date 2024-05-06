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
