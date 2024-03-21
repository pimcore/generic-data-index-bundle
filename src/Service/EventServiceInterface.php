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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Pimcore\Bundle\GenericDataIndexBundle\Event;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;

/**
 * @internal
 */
interface EventServiceInterface
{
    public function dispatchAssetSearchEvent(
        AssetSearchResultItem $asset,
        AssetPermissions $permissions
    ): Event\Asset\PermissionEvent;

    public function dispatchDataObjectSearchEvent(
        DataObjectSearchResultItem $dataObject,
        DataObjectPermissions $permissions
    ): Event\DataObject\PermissionEvent;

    public function dispatchDocumentSearchEvent(
        DocumentSearchResultItem $document,
        DocumentPermissions $permissions
    ): Event\Document\PermissionEvent;
}
