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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

/**
 * @internal
 */
enum SystemField: string
{
    use FieldCategory\SystemField\SystemFieldTrait;

    case ID = 'id';
    case ELEMENT_TYPE = 'elementType';
    case PARENT_ID = 'parentId';
    case CREATION_DATE = 'creationDate';
    case MODIFICATION_DATE = 'modificationDate';
    case PUBLISHED = 'published';
    case TYPE = 'type';
    case KEY = 'key';
    case PATH = 'path';
    case FULL_PATH = 'fullPath';
    case PATH_LEVELS = 'pathLevels';
    case PATH_LEVEL = 'pathLevel';
    case TAGS = 'tags';
    case PARENT_TAGS = 'parentTags';
    case MIME_TYPE = 'mimetype';
    case CLASS_NAME = 'className';
    case ICON = 'icon';
    case CHECKSUM = 'checksum';
    case USER_OWNER = 'userOwner';
    case USER_MODIFICATION = 'userModification';
    case LOCKED = 'locked';
    case IS_LOCKED = 'isLocked';
    case HAS_WORKFLOW_WITH_PERMISSIONS = 'hasWorkflowWithPermissions';
    case FILE_SIZE = 'fileSize';
    case DEPENDENCIES = 'dependencies';

    /**
     * Not persisted in search index but dynamically calculated
     */
    case HAS_CHILDREN = 'hasChildren';
}
