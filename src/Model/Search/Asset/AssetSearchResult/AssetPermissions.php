<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult;

final class AssetPermissions
{
    public function __construct(
        private bool $list = false,
        private bool $view = false,
        private bool $publish = false,
        private bool $delete = false,
        private bool $rename = false,
        private bool $create = false,
        private bool $settings = false,
        private bool $versions = false,
        private bool $properties = false,
    )
    {
    }

    public function isList(): bool
    {
        return $this->list;
    }

    public function setList(bool $list): AssetPermissions
    {
        $this->list = $list;
        return $this;
    }

    public function isView(): bool
    {
        return $this->view;
    }

    public function setView(bool $view): AssetPermissions
    {
        $this->view = $view;
        return $this;
    }

    public function isPublish(): bool
    {
        return $this->publish;
    }

    public function setPublish(bool $publish): AssetPermissions
    {
        $this->publish = $publish;
        return $this;
    }

    public function isDelete(): bool
    {
        return $this->delete;
    }

    public function setDelete(bool $delete): AssetPermissions
    {
        $this->delete = $delete;
        return $this;
    }

    public function isRename(): bool
    {
        return $this->rename;
    }

    public function setRename(bool $rename): AssetPermissions
    {
        $this->rename = $rename;
        return $this;
    }

    public function isCreate(): bool
    {
        return $this->create;
    }

    public function setCreate(bool $create): AssetPermissions
    {
        $this->create = $create;
        return $this;
    }

    public function isSettings(): bool
    {
        return $this->settings;
    }

    public function setSettings(bool $settings): AssetPermissions
    {
        $this->settings = $settings;
        return $this;
    }

    public function isVersions(): bool
    {
        return $this->versions;
    }

    public function setVersions(bool $versions): AssetPermissions
    {
        $this->versions = $versions;
        return $this;
    }

    public function isProperties(): bool
    {
        return $this->properties;
    }

    public function setProperties(bool $properties): AssetPermissions
    {
        $this->properties = $properties;
        return $this;
    }

}