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

enum RefreshIndexMode: string
{
    case REFRESH = 'true';
    case NOT_REFRESH = 'false';
    case WAIT_FOR = 'wait_for';
}
