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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;

interface IndexElementIndexServiceInterface
{
    public function updateSiblings(AbstractObject|Document $element, string $elementType): void;

    public function resetChildrenIndexBy(AbstractObject $element): void;
}
