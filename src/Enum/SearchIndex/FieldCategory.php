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
enum FieldCategory: string
{
    case SYSTEM_FIELDS = 'system_fields';
    case STANDARD_FIELDS = 'standard_fields';
    case CUSTOM_FIELDS = 'custom_fields';
    case INHERITED_FIELDS = '_inherited_fields';
}
