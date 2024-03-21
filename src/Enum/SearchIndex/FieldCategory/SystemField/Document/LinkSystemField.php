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

enum LinkSystemField: string
{
    use SystemFieldTrait;

    case INTERNAL = 'internal';
    case INTERNAL_TYPE = 'internalType';
    case DIRECT = 'direct';
    case LINK_TYPE = 'linktype';
    case HREF = 'href';
}
