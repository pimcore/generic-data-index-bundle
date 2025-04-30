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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;

interface SearchProviderInterface
{
    public function createAssetSearch(): AssetSearchInterface;

    public function createDataObjectSearch(): DataObjectSearchInterface;

    public function createDocumentSearch(): DocumentSearchInterface;

    public function createElementSearch(): ElementSearchInterface;
}
