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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validator\ValidPathStringTrait;

final class PathFilter implements SearchModifierInterface
{
    use ValidPathStringTrait;

    public function __construct(
        private readonly string $path,
        private readonly bool $directChildrenOnly = false,
        private readonly bool $includeParentItem = false,
    ) {
        $this->validatePathString($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
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
