<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch;

enum WildcardFilterMode
{
    case BOTH; // *term*
    case PREFIX; // *term
    case SUFFIX; // term*
    case NONE; // term
}