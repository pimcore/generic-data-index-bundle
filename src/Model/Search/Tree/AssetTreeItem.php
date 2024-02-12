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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree;

/**
 * @deprecated
 */
final class AssetTreeItem
{
    public function __construct(
        private readonly int $id,
        private readonly string $filename,
        private readonly bool $children,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function hasChildren(): bool
    {
        return $this->children;
    }
}
