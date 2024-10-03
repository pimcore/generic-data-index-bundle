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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AssetSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\DocumentSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

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
