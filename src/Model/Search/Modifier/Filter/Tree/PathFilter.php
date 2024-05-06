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
use Pimcore\ValueObject\String\Path;

final class PathFilter implements SearchModifierInterface
{
    private Path $path;

    public function __construct(
        string $path = '/',
        private readonly bool $directChildrenOnly = false,
        private readonly bool $includeParentItem = false,
    ) {
        $this->path = new Path($path);
    }

    public function getPath(): string
    {
        return $this->path->getValue();
    }

    public function getPathWithoutTrailingSlash(): string
    {
        return rtrim($this->getPath(), '/');
    }

    public function getPathWithTrailingSlash(): string
    {
        return $this->getPathWithoutTrailingSlash() . '/';
    }

    public function isDirectChildrenOnly(): bool
    {
        return $this->directChildrenOnly;
    }

    public function isIncludeParentItem(): bool
    {
        return $this->includeParentItem;
    }
}
