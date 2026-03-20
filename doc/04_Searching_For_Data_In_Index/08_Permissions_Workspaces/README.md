# Permissions and Workspaces

The Generic Data Index search services respect user permissions and workspace
configurations associated with their roles.

The search services attach workspace permissions to queries via search modifiers
and include them in each result item.

## Permission Manipulation

Override workspace permissions for specific elements via events:

- `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent` (assets)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\PermissionEvent` (data objects)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\Document\PermissionEvent` (documents)

### Example: Restrict Asset Permissions by Path

This event subscriber restricts `view` and `list` permissions for a specific asset path:

```php
<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetPermissionSubscriber implements EventSubscriberInterface
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

In addition to workspace permissions, the search services check user-level permissions
(assets, objects, documents) before constructing the search query.
