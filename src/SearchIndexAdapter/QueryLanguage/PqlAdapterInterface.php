<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\Query;

/**
 * @internal
 */
interface PqlAdapterInterface
{
    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array;

    public function translateToQueryStringQuery(string $query): array;
}