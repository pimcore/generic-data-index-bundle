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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;

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
    public function getElementByType(int $id, string $type): Asset|AbstractObject|Document|null;

    /**
     * @throws InvalidElementTypeException
     */
    public function getElementType(ElementInterface $element): ElementType;

    public function classDefinitionExists(string $name): bool;
}
