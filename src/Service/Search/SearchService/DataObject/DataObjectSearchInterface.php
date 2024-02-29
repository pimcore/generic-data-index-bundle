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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Model\User;

interface DataObjectSearchInterface
{

    public function search(SearchInterface $assetSearch): DataObjectSearchResult;

    public function byId(int $id, ?User $user = null): ?DataObjectSearchResultItem;
}
