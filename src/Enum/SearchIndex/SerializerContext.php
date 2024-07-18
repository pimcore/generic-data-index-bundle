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

/**
 * @internal
 */
enum SerializerContext
{
    case SKIP_LAZY_LOADED_FIELDS;

    public function containedInContext(array $context): bool
    {
        return $context[$this->name] ?? false;
    }

    public function createContext(): array
    {
        return [$this->name => true];
    }
}
