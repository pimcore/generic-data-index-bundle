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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

/**
 * A search result item that carries its query-time relevance score. Kept separate from
 * {@see ElementSearchResultItemInterface} so adding score support stays backwards compatible
 * for existing implementations of that interface.
 */
interface ScoreAwareResultItemInterface
{
    /**
     * The query-time relevance score of this hit (e.g. kNN vector similarity or full-text
     * relevance), or null when the executing query produced no score - for example a filter-only
     * search, or one sorted by a field without score tracking.
     */
    public function getScore(): ?float;

    public function setScore(?float $score): ScoreAwareResultItemInterface;
}
