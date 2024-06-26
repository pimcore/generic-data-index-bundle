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
