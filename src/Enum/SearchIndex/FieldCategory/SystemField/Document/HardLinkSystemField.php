<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
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
