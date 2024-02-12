<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

interface SearchInterface
{
    /**
     * @return SearchModifierInterface[]
     */
    public function getModifiers(): array;

    public function addModifier(SearchModifierInterface $modifier): self;
}