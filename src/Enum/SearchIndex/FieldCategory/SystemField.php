<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

/**
 * @internal
 */
enum SystemField: string
{
    use SystemFieldTrait;

    case ID = 'id';
    case ELEMENT_TYPE = 'elementType';
    case PARENT_ID = 'parentId';
    case CREATION_DATE = 'creationDate';
    case MODIFICATION_DATE = 'modificationDate';
    case PUBLISHED = 'published';
    case TYPE = 'type';
    case KEY = 'key';
    case INDEX = 'index';
    case PATH = 'path';
    case FULL_PATH = 'fullPath';
    case PATH_LEVELS = 'pathLevels';
    case PATH_LEVEL = 'pathLevel';
    case TAGS = 'tags';
    case PARENT_TAGS = 'parentTags';
    case MIME_TYPE = 'mimetype';
    case CLASS_ID = 'classId';
    case CLASS_NAME = 'className';
    case CLASS_DEFINITION_ICON = 'classDefinitionIcon';
    case CHECKSUM = 'checksum';
    case USER_OWNER = 'userOwner';
    case USER_MODIFICATION = 'userModification';
    case LOCKED = 'locked';
    case HAS_WORKFLOW_WITH_PERMISSIONS = 'hasWorkflowWithPermissions';
    case FILE_SIZE = 'fileSize';
    case DEPENDENCIES = 'dependencies';
    case IS_REFERENCED = 'isReferenced';
    case CHILDREN_SORT_BY = 'childrenSortBy';
    case CHILDREN_SORT_ORDER = 'childrenSortOrder';

    /**
     * Not persisted in search index but dynamically calculated
     */
    case HAS_CHILDREN = 'hasChildren';
}
