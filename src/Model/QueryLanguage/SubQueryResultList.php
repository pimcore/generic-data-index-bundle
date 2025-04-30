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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage;

use Pimcore\ValueObject\Collection\ArrayOfPositiveIntegers;
use ValueError;

/**
 * @internal
 */
final class SubQueryResultList
{
    /**
     * @var ArrayOfPositiveIntegers[]
     */
    private array $subQueryResults = [];

    public function addResult(string $subQueryId, array $ids): void
    {
        $this->subQueryResults[$subQueryId] = new ArrayOfPositiveIntegers($ids);
    }

    public function getSubQueryResult(string $subQueryId): array
    {
        if (empty($this->subQueryResults[$subQueryId])) {
            throw new ValueError(
                sprintf('SubQueryResult with id "%s" not contained in result list', $subQueryId)
            );
        }

        return $this->subQueryResults[$subQueryId]->getValue();
    }
}
