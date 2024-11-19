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
