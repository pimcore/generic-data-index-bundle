---
title: Extending the Search Index
description: Add custom data attributes to the search index using UpdateIndexDataEvent and ExtractMappingEvent.
---

# Extending the Search Index

## Adding Custom Fields via Events

The index update process stores system fields and supported data object/asset field types
by default. Extend the index with custom attributes using the following events.

### UpdateIndexDataEvent

Store additional fields in the search index. Use the event matching your element type:

- `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent` (assets)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent` (concrete data objects)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateFolderIndexDataEvent` (data object folders)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\Document\UpdateIndexDataEvent` (documents)

An indexed document in the search index has this structure:

```json
{
    "system_fields": {
        "id": 145,
        "creationDate": "2019-05-24T15:42:20+0200",
        "modificationDate": "2019-08-23T15:15:54+0200",
        "type": "image",
        "key": "abandoned-automobile-automotive-1082654.jpg"
    },
    "standard_fields": [ ... ],
    "custom_fields": [ ]
}
```

The three sections are:

- **system_fields** - Base fields common to all elements (id, creationDate, fullPath, etc.)
- **standard_fields** - Data object fields or asset metadata supported out of the box
- **custom_fields** - Custom data added via `UpdateIndexDataEvent`.
  Added fields are automatically included in full text search (depending on mapping).

### ExtractMappingEvent

Define the search engine mapping for custom fields (see
[Elasticsearch mapping types](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-types.html)
or [OpenSearch field types](https://opensearch.org/docs/latest/field-types/)).
Use the event matching your element type:

- `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent` (assets)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractMappingEvent` (concrete data objects)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractFolderMappingEvent` (data object folders)
- `Pimcore\Bundle\GenericDataIndexBundle\Event\Document\ExtractMappingEvent` (documents)

## Example 1: Asset File Size Category

This event subscriber categorizes assets by file size into `small` (< 300 KB),
`medium` (300 KB - 3 MB), and `big` (> 3 MB):

```php
<?php

namespace App\EventListener;

use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent;
use Pimcore\Model\Asset\Folder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileSizeIndexSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UpdateIndexDataEvent::class => 'onUpdateIndexData',
            ExtractMappingEvent::class  => 'onExtractMapping',
        ];
    }

    public function onUpdateIndexData(UpdateIndexDataEvent $event): void
    {
        $asset = $event->getElement();
        if ($asset instanceof Folder) {
            return;
        }

        $customFields = $event->getCustomFields();

        $fileSize = $asset->getFileSize();
        $customFields['fileSizeSelection'] = match (true) {
            $fileSize < 300_000      => 'small',
            $fileSize <= 3_000_000   => 'medium',
            default                  => 'big',
        };

        $event->setCustomFields($customFields);
    }

    public function onExtractMapping(ExtractMappingEvent $event): void
    {
        $customFieldsMapping = $event->getCustomFieldsMapping();

        // 'keyword' works well for select and multi-select filters
        $customFieldsMapping['fileSizeSelection'] = [
            'type' => 'keyword'
        ];

        $event->setCustomFieldsMapping($customFieldsMapping);
    }
}
```

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    App\EventListener\FileSizeIndexSubscriber:
        tags:
            - { name: kernel.event_subscriber }
```

## Example 2: Data Object Variant Count

This event subscriber adds a `numberOfVariants` field to `Car` data objects,
counting direct children (variants) of each car:

```php
<?php

namespace App\EventListener;

use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent;
use Pimcore\Model\DataObject\Car;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarVariantCountSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UpdateIndexDataEvent::class => 'onUpdateIndexData',
            ExtractMappingEvent::class  => 'onExtractMapping',
        ];
    }

    public function onUpdateIndexData(UpdateIndexDataEvent $event): void
    {
        $car = $event->getElement();
        if (!$car instanceof Car) {
            return;
        }

        $customFields = $event->getCustomFields();
        $customFields['numberOfVariants'] = count($car->getChildren() ?? []);
        $event->setCustomFields($customFields);
    }

    public function onExtractMapping(ExtractMappingEvent $event): void
    {
        if ($event->getClassDefinition()->getId() !== 'CAR') {
            return;
        }

        $customFieldsMapping = $event->getCustomFieldsMapping();
        $customFieldsMapping['numberOfVariants'] = [
            'type' => 'integer'
        ];
        $event->setCustomFieldsMapping($customFieldsMapping);
    }
}
```

## Rebuild Index After Changes

After registering an event subscriber, rebuild the search index:

```bash
bin/console generic-data-index:update:index -r
```
