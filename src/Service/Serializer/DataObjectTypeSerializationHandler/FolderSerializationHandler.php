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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DataObjectTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResultItem;

class FolderSerializationHandler extends AbstractSerializationHandler
{
    public function createSearchResultModel(array $indexData): DataObjectSearchResultItem
    {
        return new DataObjectSearchResultItem\Folder();
    }
}
