<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage;

enum QueryTokenType: string
{
    case T_NONE = 'T_NONE';
    case T_INTEGER = 'T_INTEGER';
    case T_FLOAT = 'T_FLOAT';
    case T_STRING = 'T_STRING';
    case T_FIELDNAME = 'T_FIELDNAME';
    case T_RELATION_FIELD = 'T_RELATION_FIELD';
    case T_AND = 'T_AND';
    case T_OR = 'T_OR';
    case T_EQ = 'T_EQ';
    case T_GT = 'T_GT';
    case T_GTE = 'T_GTE';
    case T_LT = 'T_LT';
    case T_LTE = 'T_LTE';
    case T_LIKE = 'T_LIKE';
    case T_LPAREN = 'T_LPAREN';
    case T_RPAREN = 'T_RPAREN';
    case T_QUERY_STRING = 'T_QUERY_STRING';
}
