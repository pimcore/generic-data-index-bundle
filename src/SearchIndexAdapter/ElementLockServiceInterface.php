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
