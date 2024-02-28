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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Event;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchService;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class UserPermissionService implements UserPermissionServiceInterface
{
    public function __construct(
        private readonly AssetSearchService $assetSearchService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PermissionServiceInterface $permissionService
    ) {
    }

    public function hasPermission(User $user, string $permission): bool
    {
        $permissions = $user->getPermissions();
        if (in_array($permission, $permissions)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function isAllowed(
        string $permission,
        ElementInterface $element,
        User $user
    ): bool {
        return match (true) {
            $element instanceof Asset => $this->isAssetAllowed($permission, $element, $user),
            $element instanceof DataObject => $this->isDataObjectAllowed($element, $permission, $user),
            default => false,
        };
    }

    /**
     * @throws Exception
     */
    private function isAssetAllowed(
        string $permission,
        Asset $asset,
        User $user
    ): bool {
        $searchResult = $this->assetSearchService->byId($asset->getId(), $user);
        $event = new Event\Asset\PermissionEvent($searchResult, $permission);
        $this->eventDispatcher->dispatch($event);

        $assetPermissions = $this->permissionService->getAssetPermissions($asset->getPath(), $user);

        return $this->permissionService->getPermissionValue($assetPermissions, $permission);
    }

    /**
     * @throws Exception
     */
    private function isDataObjectAllowed(
        DataObject $dataObject,
        string $permission,
        User $user
    ): bool {
        $searchResult = null;
        $event = new Event\DataObject\PermissionEvent($searchResult, $permission);
        $this->eventDispatcher->dispatch($event);

        $assetPermissions = $this->permissionService->getDataObjectPermissions($dataObject->getPath(), $user);

        return $this->permissionService->getPermissionValue($assetPermissions, $permission);
    }
}
