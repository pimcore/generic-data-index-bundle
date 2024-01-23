<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer\AssetNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler\AssetMappingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler\MappingHandlerInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AssetTypeAdapter extends AbstractElementTypeAdapter
{

    public function __construct(
        private readonly AssetNormalizer     $normalizer,
        private readonly AssetMappingHandler $mappingExtractor,
    )
    {
    }


    public function supports(ElementInterface $element): bool
    {
        return $element instanceof Asset;
    }

    public function getIndexNameShort(ElementInterface $element): string
    {
        return IndexName::ASSET->value;
    }

    public function getAssetIndexName(): string
    {
        return $this->searchIndexConfigService->getIndexName(IndexName::ASSET->value);
    }

    public function getElementType(): string
    {
        return ElementType::ASSET->value;
    }

    public function childrenPathRewriteNeeded(ElementInterface $element): bool
    {
        return $element instanceof Asset\Folder;
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    public function getMappingHandler(): MappingHandlerInterface
    {
        return $this->mappingExtractor;
    }

}