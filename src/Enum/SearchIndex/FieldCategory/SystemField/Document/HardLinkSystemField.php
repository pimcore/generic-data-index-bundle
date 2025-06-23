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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum HardLinkSystemField: string
{
    use SystemFieldTrait;

    case SOURCE_ID = 'sourceId';
    case PROPERTIES_FROM_SOURCE = 'propertiesFromSource';
    case CHILDREN_FROM_SOURCE = 'childrenFromSource';
}
