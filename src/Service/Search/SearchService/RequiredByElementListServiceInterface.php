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
