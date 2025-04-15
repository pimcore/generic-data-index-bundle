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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Exception;
use Pimcore\Model\Element\ElementInterface;

interface ElementLockServiceInterface
{
    /**
     * Directly update locked status for children based on parent element
     *
     * @throws Exception
     */
    public function unlockPropagate(ElementInterface $element): void;

    /**
     * Check if the element is locked based on the indexed data
     */
    public function isElementLocked(string $fullPath, string $type, ?string $locked = null): bool;
}
