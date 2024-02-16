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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;

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
    case CHECKSUM = 'checksum';
    case USER_OWNER = 'userOwner';
    case USER_MODIFICATION = 'userModification';
    case LOCKED = 'locked';
    case IS_LOCKED = 'isLocked';
    case HAS_WORKFLOW_WITH_PERMISSIONS = 'hasWorkflowWithPermissions';
    case FILE_SIZE = 'fileSize';

    /**
     * Not persisted in search index but dynamically calculated
     */
    case HAS_CHILDREN = 'hasChildren';

    public function getPath(string $subField = null): string
    {
        $path = FieldCategory::SYSTEM_FIELDS->value . '.' . $this->value;

        if($subField) {
            $path .= '.' . $subField;
        }

        return $path;
    }

    public function getData(array $searchResultHit): mixed
    {
        return $searchResultHit[FieldCategory::SYSTEM_FIELDS->value][$this->value] ?? null;
    }
}
