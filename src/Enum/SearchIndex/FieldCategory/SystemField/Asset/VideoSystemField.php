<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum VideoSystemField: string
{
    use SystemFieldTrait;

    case IMAGE_THUMBNAIL = 'imageThumbnail';
    case DURATION = 'duration';
    case WIDTH = 'width';
    case HEIGHT = 'height';
}