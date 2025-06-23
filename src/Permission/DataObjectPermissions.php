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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission;

/**
 * @internal
 */
final class DataObjectPermissions extends BasePermissions
{
    private bool $save = self::DEFAULT_VALUE;

    private bool $unpublish = self::DEFAULT_VALUE;

    private ?string $localizedEdit = null;

    private ?string $localizedView = null;

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

    public function getClassProperties(array $properties = []): array
    {
        return parent::getClassProperties(get_object_vars($this));
    }
}
