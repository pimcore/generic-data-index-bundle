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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\ValueObject\Collection\ArrayOfPositiveIntegers;

final class TagFilter implements SearchModifierInterface
{
    private ArrayOfPositiveIntegers $tagIds;

    public function __construct(
        array $tagIds,
        private readonly bool $includeChildTags = false
    ) {
        $this->tagIds = new ArrayOfPositiveIntegers($tagIds);
    }

    public function getTagIds(): array
    {
        return $this->tagIds->getValue();
    }

    public function isIncludeChildTags(): bool
    {
        return $this->includeChildTags;
    }
}
