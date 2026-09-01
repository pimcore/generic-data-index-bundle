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

namespace Pimcore\Bundle\GenericDataIndexBundle\Message;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;

/**
 * @internal
 */
final readonly class UpdateSiblingsMessage
{
    public function __construct(
        private int $elementId,
        private ElementType $elementType,
        private bool $resetChildrenIndexBy
    ) {
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function getElementType(): ElementType
    {
        return $this->elementType;
    }

    public function getResetChildrenIndexBy(): bool
    {
        return $this->resetChildrenIndexBy;
    }
}
