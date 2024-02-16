<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class ElementKeySearch implements SearchModifierInterface
{
    public function __construct(
        private readonly ?string $searchTerm,
    )
    {
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }
}