<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum IndexName: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'data-object';
}