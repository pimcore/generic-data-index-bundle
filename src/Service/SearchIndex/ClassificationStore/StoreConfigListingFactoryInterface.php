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
 * The listings of the classification store configuration, so that nothing in this bundle has to build a model
 * listing itself.
 *
 * @internal
 */
interface StoreConfigListingFactoryInterface
{
    public function createGroupListing(): GroupListing;

    public function createKeyGroupRelationListing(): KeyGroupRelationListing;
}
