<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum ImageSystemField: string
{
    use SystemFieldTrait;

    case THUMBNAIL = 'thumbnail';
    case WIDTH = 'width';
    case HEIGHT = 'height';
}