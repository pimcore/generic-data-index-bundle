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
