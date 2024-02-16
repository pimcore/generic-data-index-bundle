<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\Search;

enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
