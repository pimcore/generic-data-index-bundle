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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Twig\Extension\Templating\Placeholder\Exception;

/**
 * @internal
 */
final readonly class AssetFilters
{
    public function __construct(private FieldDefinitionServiceInterface $fieldDefinitionService)
    {
    }

    /**
     * @throws InvalidModifierException
     */
    #[AsSearchModifierHandler]
    public function handleAssetMetaDataFilter(
        AssetMetaDataFilter $assetMetaDataFilter,
        SearchModifierContextInterface $context
    ): void {
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
        } catch (Exception $e) {
            throw new InvalidModifierException($e->getMessage(), 0, $e);
        }
    }
}
