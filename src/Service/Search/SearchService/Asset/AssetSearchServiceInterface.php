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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ValidationFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Pimcore\Model\User;

interface AssetSearchServiceInterface
{
    /**
     * @throws ValidationFailedException
     */
    public function search(PaginatedSearchInterface $assetSearch): AssetSearchResult;

    /**
     * @throws ValidationFailedException
     */
    public function byId(int $id, ?User $user = null): ?AssetSearchResultItem;
}
