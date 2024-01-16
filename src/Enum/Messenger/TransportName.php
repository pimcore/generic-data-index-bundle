<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger;

enum TransportName: string
{
    case INDEX_QUEUE = 'pimcore_generic_data_index_queue';
    case SYNC = 'pimcore_generic_data_index_sync';
}
