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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;

/**
 * @internal
 */
final class SearchProvider implements SearchProviderInterface
{
    public function createAssetSearch(): AssetSearchInterface
    {
        return new AssetSearch();
    }

    public function createDataObjectSearch(): DataObjectSearchInterface
    {
        return new DataObjectSearch();
    }

    public function createDocumentSearch(): DocumentSearchInterface
    {
        return new DocumentSearch();
    }

    public function createElementSearch(): ElementSearchInterface
    {
        return new ElementSearch();
    }
}
