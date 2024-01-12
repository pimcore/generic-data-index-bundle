<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum ElementType: string
{
    case ASSET = 'asset';
    case DATA_OBJECT = 'dataObject';

    case DOCUMENT = 'document';
}
