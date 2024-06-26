<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject;

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
     * @throws DataObjectSearchException
     */
    public function byId(int $id, ?User $user = null): ?DataObjectSearchResultItem;
}
