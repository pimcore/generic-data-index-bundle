<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum DocumentSystemField: string
{
    use SystemFieldTrait;

    case IMAGE_THUMBNAIL = 'imageThumbnail';
    case PAGE_COUNT = 'pageCount';
    case TEXT = 'text';
}