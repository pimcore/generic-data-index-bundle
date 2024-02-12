<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits\PaginatedSearchTrait;

final class AssetSearch implements PaginatedSearchInterface
{
    use PaginatedSearchTrait;

    /**
     * @var SearchModifierInterface[]
     */
    private array $modifiers = [];


    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    public function addModifier(SearchModifierInterface $modifier): self
    {
        $this->modifiers[] = $modifier;
        return $this;
    }


}