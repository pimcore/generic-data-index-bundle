<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;

/**
 * @internal
 */
final class StoreConfigListingFactory implements StoreConfigListingFactoryInterface
{
    public function createGroupListing(): GroupListing
    {
        return new GroupListing();
    }

    public function createKeyGroupRelationListing(): KeyGroupRelationListing
    {
        return new KeyGroupRelationListing();
    }
}
