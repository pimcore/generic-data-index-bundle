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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree\AssetTreeItemList;

/**
 * @deprecated
 */
interface AssetTreeServiceInterface
{
    public function fetchTreeItems(int $parentId = 1, int $page = 1, int $pageSize = 50): AssetTreeItemList;
}
