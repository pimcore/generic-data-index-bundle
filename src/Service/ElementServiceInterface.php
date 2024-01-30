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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;

/**
 * @internal
 */
interface ElementServiceInterface
{
    /**
     * Get element by type. Returns null if element not found.
     *
     * @throws InvalidElementTypeException
     */
    public function getElementByType(int $id, string $type): Asset|AbstractObject|null;
}
