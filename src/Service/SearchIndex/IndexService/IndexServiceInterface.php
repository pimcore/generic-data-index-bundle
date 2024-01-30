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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexServiceInterface
{
    public function isPerformIndexRefresh(): bool;

    public function setPerformIndexRefresh(bool $performIndexRefresh): IndexService;

    /**
     * @throws IndexDataException
     */
    public function updateIndexData(ElementInterface $element): IndexService;

    public function deleteFromIndex(ElementInterface $element): IndexService;
}
