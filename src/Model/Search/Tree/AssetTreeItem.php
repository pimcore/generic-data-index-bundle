<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree;

final class AssetTreeItem
{
    public function __construct(
        private readonly int    $id,
        private readonly string $filename,
        private readonly bool   $children,
    )
    {
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