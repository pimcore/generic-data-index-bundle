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
enum IndexName: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'data-object';
    case DATA_OBJECT_FOLDER = 'data-object-folder';
    case DOCUMENT = 'document';
    case ELEMENT_SEARCH = 'element-search';
}
