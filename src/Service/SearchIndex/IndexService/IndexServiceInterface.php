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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
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
}
