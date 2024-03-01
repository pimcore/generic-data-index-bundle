<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;

/**
 * @internal
 */
final class AssetFilters
{
    public function __construct(private readonly FieldDefinitionServiceInterface $fieldDefinitionService)
    {
    }

    /**
     * @throws InvalidModifierException
     */
    #[AsSearchModifierHandler]
    public function handleAssetMetaDataFilter(AssetMetaDataFilter $assetMetaDataFilter, SearchModifierContextInterface $context): void
    {
        $adapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($assetMetaDataFilter->getType());

        if ($adapter === null) {
            throw new InvalidModifierException(
                sprintf(
                    'Unsupported meta data filter type "%s"',
                    $assetMetaDataFilter->getType()
                )
            );
        }

        try {
            $adapter->applySearchFilter($assetMetaDataFilter, $context->getSearch());
        } catch (InvalidValueException $e) {
            throw new InvalidModifierException($e->getMessage(), 0, $e);
        }
    }
}