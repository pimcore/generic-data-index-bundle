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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Model\Element\ElementInterface;

interface RequiredByElementListServiceInterface
{
    /**
     * Returns array of IDs,element types and index name of all elements which require passed element ($element).
     *
     * @return HitData[]
     */
    public function getDependencyList(ElementInterface $element, ?ElementSearchInterface $search = null): array;

    /**
     * @return HitData[]
     */
    public function getDependencyListForCurrentPage(
        ElementInterface $element,
        ElementSearchInterface $search
    ): array;
}
