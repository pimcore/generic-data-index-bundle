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
