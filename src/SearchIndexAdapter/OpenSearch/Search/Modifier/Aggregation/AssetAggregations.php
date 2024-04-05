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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Twig\Extension\Templating\Placeholder\Exception;

/**
 * @internal
 */
final readonly class AssetAggregations
{
    public function __construct(private FieldDefinitionServiceInterface $fieldDefinitionService)
    {
    }

    #[AsSearchModifierHandler]
    public function handleAssetMetaDataAggregation(
        AssetMetaDataAggregation $assetMetaDataAggregation,
        SearchModifierContextInterface $context
    ): void {
        $adapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($assetMetaDataAggregation->getType());

        if ($adapter === null) {
            throw new InvalidModifierException(
                sprintf(
                    'Unsupported meta data filter type "%s"',
                    $assetMetaDataAggregation->getType()
                )
            );
        }

        try {
            $aggregation = $adapter->getSearchFilterAggregation($assetMetaDataAggregation);

            if ($aggregation === null) {
                throw new InvalidModifierException(
                    sprintf(
                        'Meta data filter for type "%s" does not support aggregation.',
                        $assetMetaDataAggregation->getType()
                    )
                );
            }
            $context->getSearch()->addAggregation($aggregation);
        } catch (Exception $e) {
            throw new InvalidModifierException($e->getMessage(), 0, $e);
        }
    }
}
