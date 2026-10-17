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
 * What the copy into the new index does with a field whose mapping changed.
 *
 * @internal
 */
enum MappingFieldChangeAction: string
{
    /** The old values fit the new mapping; they stay in the copy until the rebuild replaces them. */
    case KEEP = 'keep';

    /** The old values do not fit, or would be converted wrongly; the field is removed in the copy. */
    case STRIP = 'strip';
}
