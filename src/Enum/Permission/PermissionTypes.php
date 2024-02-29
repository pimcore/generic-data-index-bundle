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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission;

/**
 * @internal
 */
enum PermissionTypes: string
{
    case LIST = 'list';
    case VIEW = 'view';
    case PUBLISH = 'publish';
    case DELETE = 'delete';
    case RENAME = 'rename';
    case CREATE = 'create';
    case SETTINGS = 'settings';
    case VERSIONS = 'versions';
    case PROPERTIES = 'properties';
}
