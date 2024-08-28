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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexServiceInterface
{
    /**
     * @throws IndexDataException
     */
    public function updateIndexData(ElementInterface $element): IndexService;

    public function deleteFromIndex(ElementInterface $element): IndexService;

    public function deleteFromSpecificIndex(string $indexName, int $elementId): IndexService;

    public function updateAssetDependencies(Asset $asset): array;
}
