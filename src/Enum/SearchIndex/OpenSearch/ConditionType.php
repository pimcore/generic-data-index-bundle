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
enum ConditionType: string
{
    case EXISTS = 'exists';
    case FILTER = 'filter';
    case MUST = 'must';
    case MUST_NOT = 'must_not';
    case SHOULD = 'should';
}
