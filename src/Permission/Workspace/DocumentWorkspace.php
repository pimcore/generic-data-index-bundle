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

namespace Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
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
            workspacePermissions: new DocumentPermissions()
        );
        parent::__construct($documentPermissions->getCpath());
    }
}
