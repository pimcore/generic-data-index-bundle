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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Model\User;

interface DataObjectSearchServiceInterface
{
    /**
     * @throws DataObjectSearchException
     */
    public function search(DataObjectSearchInterface $dataObjectSearch): DataObjectSearchResult;

    /**
     * @throws Exception
     */
    public function byId(int $id, ?User $user = null): ?DataObjectSearchResultItem;
}
