<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum IndexQueueOperation: string
{
    case UPDATE = 'update';
    case DELETE = 'delete';
}
