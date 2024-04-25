<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final readonly class Pql implements SearchModifierInterface
{
    public function __construct(
        private string $query,
    )
    {
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}