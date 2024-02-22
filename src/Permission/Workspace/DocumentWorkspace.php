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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class DocumentWorkspace extends AbstractWorkspace
{
    public const WORKSPACE_TYPE = 'document';

    public function __construct(
        Workspace\Document $documentPermissions
    ) {
        $this->setWorkspacePermissions(
            userPermissions: $documentPermissions,
            workspacePermissions: new DocumentPermission()
        );
        parent::__construct($documentPermissions->getCpath());
    }
}
