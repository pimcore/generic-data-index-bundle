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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

/**
 * @internal
 */
enum SystemField: string
{
    case ID = 'id';
    case PARENT_ID = 'parentId';
    case CREATION_DATE = 'creationDate';
    case MODIFICATION_DATE = 'modificationDate';
    case PUBLISHED = 'published';
    case TYPE = 'type';
    case KEY = 'key';
    case PATH = 'path';
    case FULL_PATH = 'fullPath';
    case PATH_LEVELS = 'pathLevels';
    case TAGS = 'tags';
    case MIME_TYPE = 'mimetype';
    case CLASS_NAME = 'className';

    //case NAME = 'name';
    //case THUMBNAIL = 'thumbnail';
    case CHECKSUM = 'checksum';

    //case COLLECTIONS = 'collections';
    //case PUBLIC_SHARES = 'publicShares';
    case USER_OWNER = 'userOwner';
    case HAS_WORKFLOW_WITH_PERMISSIONS = 'hasWorkflowWithPermissions';
    case FILE_SIZE = 'fileSize';
}
