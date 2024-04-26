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
