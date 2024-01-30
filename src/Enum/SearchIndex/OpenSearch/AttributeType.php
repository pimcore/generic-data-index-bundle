<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch;

/**
 * @internal
 */
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
