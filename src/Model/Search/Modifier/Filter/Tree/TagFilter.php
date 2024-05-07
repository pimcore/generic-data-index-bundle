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
