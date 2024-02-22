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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission;

/**
 * @internal
 */
final class DataObjectPermission extends BasePermissions
{
    private bool $save = self::DEFAULT_VALUE;

    private bool $unpublish = self::DEFAULT_VALUE;

    private ?string $localizedEdit = null;

    private ?string $localizedView = null;

    private ?string $layouts = null;

    public function isSave(): bool
    {
        return $this->save;
    }

    public function setSave(bool $save): void
    {
        $this->save = $save;
    }

    public function isUnpublish(): bool
    {
        return $this->unpublish;
    }

    public function setUnpublish(bool $unpublish): void
    {
        $this->unpublish = $unpublish;
    }

    public function isLocalizedEdit(): ?string
    {
        return $this->localizedEdit;
    }

    public function setLocalizedEdit(?string $localizedEdit): void
    {
        $this->localizedEdit = $localizedEdit;
    }

    public function isLocalizedView(): ?string
    {
        return $this->localizedView;
    }

    public function setLocalizedView(?string $localizedView): void
    {
        $this->localizedView = $localizedView;
    }

    public function isLayouts(): ?string
    {
        return $this->layouts;
    }

    public function setLayouts(?string $layout): void
    {
        $this->layouts = $layout;
    }

    public function getClassProperties(array $properties = []): array
    {
        return parent::getClassProperties(get_object_vars($this));
    }
}
