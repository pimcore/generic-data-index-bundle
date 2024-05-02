<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum IndexType: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'dataObject';
    case DOCUMENT = 'document';
}