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
