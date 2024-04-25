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

/**
 * @internal
 */
interface PqlAdapterInterface
{
    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array;

    public function translateToQueryStringQuery(string $query): array;
}
