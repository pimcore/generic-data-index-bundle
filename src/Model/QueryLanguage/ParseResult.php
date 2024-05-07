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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage;

/**
 * @internal
 */
final readonly class ParseResult
{
    public function __construct(
        private array|ParseResultSubQuery $query,
        private array $subQueries
    ) {
    }

    public function getQuery(): array|ParseResultSubQuery
    {
        return $this->query;
    }

    /**
     * @return ParseResultSubQuery[]
     */
    public function getSubQueries(): array
    {
        return $this->subQueries;
    }
}
