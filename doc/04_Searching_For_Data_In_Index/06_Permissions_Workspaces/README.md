# Permissions and Workspaces
The Generic Data Index bundle respects the user permissions and user workspaces in connection to his roles.

User workspace permissions are respected by the search service and are attached by using the search modifier to the search query. 
These permissions are then returned as a part of the search result item.

## Permission manipulation
If there is a need to manipulate the workspace permissions, e.g. for specific asset path, it is possible to do so via events:
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\PermisisonEvent` for data objects
- `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermisisonEvent` for assets
- `Pimcore\Bundle\GenericDataIndexBundle\Event\Document\PermisisonEvent` for documents

You can define your event listener and adapt the permissions as needed. In this example we want to restrict `view` and `list` permissions for a specific asset path:
```php
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

namespace AppBundle\EventSubscriber;

use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class AssetPermissionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PermissionEvent::class => 'adaptPermissions',
        ];
    }

    public function adaptPermissions(PermissionEvent $event): void
    {
        $permissions = $event->getPermissions();

        $asset = $event->getElement();
        if ($asset->getFullPath() === '/path/to/your/asset') {
            $permissions->setView(false);
            $permissions->setList(false);
        }

        $event->setPermissions($permissions);
    }
}
```

## User Permissions
Additionally, to the workspace permissions, the user permissions are also respected by the search service. 
The user permissions (assets, objects, documents) are checked before the search query is constructed.