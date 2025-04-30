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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex;

final readonly class HitData
{
    public function __construct(
        private string $id,
        private string $elementType,
        private string $index
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getElementType(): string
    {
        return $this->elementType;
    }

    public function getIndex(): string
    {
        return $this->index;
    }
}
