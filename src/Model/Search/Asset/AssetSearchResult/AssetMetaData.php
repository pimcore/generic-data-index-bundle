<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult;

final class AssetMetaData
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $language,
        private readonly string $type,
        private readonly mixed $data,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}