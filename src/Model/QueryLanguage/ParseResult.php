<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage;

/**
 * @internal
 */
final readonly class ParseResult
{
    public function __construct(
        private array $query,
        private array $subQueries
    )
    { }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getSubQueries(): array
    {
        return $this->subQueries;
    }

}