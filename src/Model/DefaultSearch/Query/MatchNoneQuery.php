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
use stdClass;

/**
 * A filter that matches no document, for a condition the index can answer without being asked - an
 * address the mapping does not declare cannot hold a value for any element.
 *
 * It is not the same as adding no query at all, and not the same as negating one. A filter that is
 * skipped widens the result to everything the other filters allow, and a negated one matches every
 * document the negated clause misses; both are the opposite of what an unsatisfiable condition means.
 *
 * The clause body is an stdClass rather than an empty array so that it encodes as the JSON object
 * `{}` the query DSL expects - an empty PHP array would encode as `[]`.
 */
final class MatchNoneQuery extends BoolQuery implements AsSubQueryInterface
{
    private const MATCH_NONE = 'match_none';

    public function __construct()
    {
        parent::__construct([
            ConditionType::FILTER->value => [
                self::MATCH_NONE => new stdClass(),
            ],
        ]);
    }

    public function toArrayAsSubQuery(): array
    {
        return [
            self::MATCH_NONE => new stdClass(),
        ];
    }
}
