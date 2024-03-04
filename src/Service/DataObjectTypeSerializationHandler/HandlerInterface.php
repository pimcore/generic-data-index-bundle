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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\DataObjectTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Model\DataObject;

interface HandlerInterface
{
    public function getAdditionalSystemFields(DataObject $dataObject): array;

    /**
     * Type independent attributes will be added in DataObjectSearchResultDenormalizer
     */
    public function createSearchResultModel(array $indexData): DataObjectSearchResultItem;
}
