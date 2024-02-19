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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum DocumentSystemField: string
{
    use SystemFieldTrait;

    case IMAGE_THUMBNAIL = 'imageThumbnail';
    case PAGE_COUNT = 'pageCount';
    case TEXT = 'text';
}
