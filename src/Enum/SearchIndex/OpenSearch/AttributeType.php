<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch;

enum AttributeType: string
{
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case DATE = 'date';
    case FLOAT = 'float';
    case INTEGER = 'integer';
    case LONG = 'long';
    case NESTED = 'nested';
    case OBJECT = 'object';
    case BOOLEAN = 'boolean';
}
