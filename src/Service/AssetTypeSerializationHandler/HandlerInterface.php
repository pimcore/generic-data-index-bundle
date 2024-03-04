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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Model\Asset;

interface HandlerInterface
{
    public function getAdditionalSystemFields(Asset $asset): array;

    /**
     * Type independent attributes will be added in AssetSearchResultDenormalizer
     */
    public function createSearchResultModel(array $indexData): AssetSearchResultItem;
}
