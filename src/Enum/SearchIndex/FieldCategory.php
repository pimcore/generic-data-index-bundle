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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum FieldCategory: string
{
    case SYSTEM_FIELDS = 'system_fields';
    case STANDARD_FIELDS = 'standard_fields';
    case CUSTOM_FIELDS = 'custom_fields';
}
