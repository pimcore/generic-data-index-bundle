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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum ElementType: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'dataObject';
    case DOCUMENT = 'document';

    public function getShortValue(): string
    {
        return match ($this) {
            self::DATA_OBJECT => 'object',
            default => $this->value,
        };
    }

    public static function fromShortValue(string $shortValue): self
    {
        return match ($shortValue) {
            'object' => self::DATA_OBJECT,
            default => self::from($shortValue),
        };
    }
}
