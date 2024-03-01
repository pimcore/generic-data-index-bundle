<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class AssetMetaDataFilter implements SearchModifierInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly mixed $data,
        private readonly ?string $language = null,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }
}