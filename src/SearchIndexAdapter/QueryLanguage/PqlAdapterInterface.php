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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\SubQueryResultList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
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
     *
     * @throws ParsingException
     */
    public function processSubQueries(
        ProcessorInterface $processor,
        string $originalQuery,
        array $subQueries
    ): SubQueryResultList;

    public function transformSubQuery(ParseResultSubQuery $subQuery, SubQueryResultList $subQueryResults): array;

    /**
     * Transforms the field name to the format/structure used in the search index.
     * E.g. transforms "id" to "system_fields.id"
     */
    public function transformFieldName(
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity,
        bool $sort = false
    ): string;

    /**
     * Returns a error message if the field name is invalid
     */
    public function validateFieldName(
        string $originalFieldName,
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity = null
    ): ?string;
}
