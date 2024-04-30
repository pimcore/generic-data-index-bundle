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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\SubQueryResultList;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;

/**
 * @internal
 */
interface PqlAdapterInterface
{
    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array;

    public function translateToQueryStringQuery(string $query): array;

    /**
     * @param ParseResultSubQuery[] $subQueries
     */
    public function processSubQueries(ProcessorInterface $processor, array $subQueries): SubQueryResultList;

    public function transformSubQuery(ParseResultSubQuery $subQuery, SubQueryResultList $subQueryResults): array;

    /**
     * Transforms the field name to the format/structure used in the search index.
     * E.g. transforms "id" to "system_fields.id"
     */
    public function transformFieldName(string $fieldName, array $indexMapping): string;
}
