<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch;
enum QueryType: string
{
    case BOOL = 'bool';
    case TERMS = 'terms';
}
