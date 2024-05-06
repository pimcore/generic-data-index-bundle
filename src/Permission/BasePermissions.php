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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission;

/**
 * @internal
 */
abstract class BasePermissions
{
    // TODO: Change default value once development of API is finished
    protected const DEFAULT_VALUE = true;

    private bool $list = self::DEFAULT_VALUE;

    private bool $view = self::DEFAULT_VALUE;

    private bool $publish = self::DEFAULT_VALUE;

    private bool $delete = self::DEFAULT_VALUE;

    private bool $rename = self::DEFAULT_VALUE;

    private bool $create = self::DEFAULT_VALUE;

    private bool $settings = self::DEFAULT_VALUE;

    private bool $versions = self::DEFAULT_VALUE;

    private bool $properties = self::DEFAULT_VALUE;

    public function isList(): bool
    {
        return $this->list;
    }

    public function setList(bool $list): void
    {
        $this->list = $list;
    }

    public function isView(): bool
    {
        return $this->view;
    }

    public function setView(bool $view): void
    {
        $this->view = $view;
    }

    public function isPublish(): bool
    {
        return $this->publish;
    }

    public function setPublish(bool $publish): void
    {
        $this->publish = $publish;
    }

    public function isDelete(): bool
    {
        return $this->delete;
    }

    public function setDelete(bool $delete): void
    {
        $this->delete = $delete;
    }

    public function isRename(): bool
    {
        return $this->rename;
    }

    public function setRename(bool $rename): void
    {
        $this->rename = $rename;
    }

    public function isCreate(): bool
    {
        return $this->create;
    }

    public function setCreate(bool $create): void
    {
        $this->create = $create;
    }

    public function isSettings(): bool
    {
        return $this->settings;
    }

    public function setSettings(bool $settings): void
    {
        $this->settings = $settings;
    }

    public function isVersions(): bool
    {
        return $this->versions;
    }

    public function setVersions(bool $versions): void
    {
        $this->versions = $versions;
    }

    public function isProperties(): bool
    {
        return $this->properties;
    }

    public function setProperties(bool $properties): void
    {
        $this->properties = $properties;
    }

    public function getClassProperties(array $properties = []): array
    {
        return array_merge(get_object_vars($this), $properties);
    }
}
