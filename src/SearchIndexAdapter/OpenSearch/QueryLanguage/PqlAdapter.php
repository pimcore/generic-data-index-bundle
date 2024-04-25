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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;

/**
 * @internal
 */
final class PqlAdapter implements PqlAdapterInterface
{
    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array
    {
        return match($operator) {
            QueryTokenType::T_EQ => ['match' => [$field => $value]],
            QueryTokenType::T_GT => ['range' => [$field => ['gt' => $value]]],
            QueryTokenType::T_LT => ['range' => [$field => ['lt' => $value]]],
            QueryTokenType::T_GTE => ['range' => [$field => ['gte' => $value]]],
            QueryTokenType::T_LTE => ['range' => [$field => ['lte' => $value]]],
            QueryTokenType::T_LIKE => ['wildcard' => [$field => str_replace('*', '?', $value)]],
            default => throw new InvalidArgumentException('Unknown operator: ' . $operator->value)
        };
    }

    public function translateToQueryStringQuery(string $query): array
    {
        return ['query_string' => ['query' => $query]];
    }
}
