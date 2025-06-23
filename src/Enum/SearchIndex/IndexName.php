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
enum IndexName: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'data-object';
    case DATA_OBJECT_FOLDER = 'data-object-folder';
    case DOCUMENT = 'document';
    case ELEMENT_SEARCH = 'element-search';
}
