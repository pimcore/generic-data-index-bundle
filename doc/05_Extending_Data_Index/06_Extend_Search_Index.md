# Extending Search Index

## Extending Search Index via Events

The regular index update process stores a defined set of standard data types in the data index which makes it
possible to find, filter, sort and list them in the portal engine.

It is possible to extend the index with custom attributes if needed. For this purpose the following events exist. You
will find code examples at the end of this section.

### UpdateIndexDataEvent

This event can be used to store additional fields in the search index. Depending on if you would like to index additional
data for assets or data objects use one of the following two events.

* `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent`
* `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent`

If you take a look at the source of an indexed document within data index (e.g. OpenSearch) you will find a structure like this:

```json
{
          "system_fields" : {
            "id" : 145,
            "creationDate" : "2019-05-24T15:42:20+0200",
            "modificationDate" : "2019-08-23T15:15:54+0200",
            "type" : "image",
            "key" : "abandoned-automobile-automotive-1082654.jpg",
            ...
          },
          "standard_fields" : [ ... ],
          "custom_fields" : [ ]
}
```

This is used to separate the data into three sections:

###### system_fields

Base system fields which are the same for all assets or data objects (like id, creationDate, fullPath...).

###### standard_fields

All data object or asset metadata types which are supported out of the box depending on your data model.

###### custom_fields

This is the place where you are able to add data via the `UpdateIndexDataEvent`. As soon as additional fields are added
they are searchable through the full text search (depending on the mapping of the fields).

### ExtractMappingEvent

With this event it's possible to define the [mapping](https://opensearch.org/docs/latest/field-types/)
of the additional custom fields. Again there are separate events for assets and data objects.

* `Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent`
* `Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractMappingEvent`


### Example 1: Assets

The following example creates an EventSubscriber which adds another custom field. The logic applies to assets and divides the assets into file size groups:

* small: < 300KB
* medium: 300KB - 3MB
* big: > 3MB

```php
<?php

namespace AppBundle\EventListener;

use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent;
use Pimcore\Model\Asset\Folder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileSizeIndexSubscriber implements EventSubscriberInterface
{
    
    public static function getSubscribedEvents()
    {
        return [
            UpdateIndexDataEvent::class  => 'onUpdateIndexData',
            ExtractMappingEvent::class   => 'onExtractMapping',
        ];
    }

    public function onUpdateIndexData(UpdateIndexDataEvent $event)
    {
        $asset = $event->getAsset();
        if($asset instanceof Folder) {
            return;
        }

        // Ensure that you take the original array and extend it.
        $customFields = $event->getCustomFields();

        $fileSize = $event->getAsset()->getFileSize();
        $fileSizeSelection = null;
        if($fileSize < 3*1000) {
            $fileSizeSelection = 'small';
        } elseif($fileSize <= 3*1000*1000) {
            $fileSizeSelection = 'medium';
        } else {
            $fileSizeSelection = 'big';
        }

        $customFields['fileSizeSelection'] = $fileSizeSelection;

        $event->setCustomFields($customFields);
    }

    public function onExtractMapping(ExtractMappingEvent $event)
    {
        // Ensure that you take the original array and extend it.
        $customFieldsMapping = $event->getCustomFieldsMapping();

        /**
         * Take a look at the OpenSearch docs how mapping works.
         * A 'keyword' field would be best for regular select and multi select filters.
         * For full text search it is possible to define sub-fields with special OpenSearch analyzers too.
         */
        $customFieldsMapping['fileSizeSelection'] = [
            'type' => 'keyword'
        ];

        $event->setCustomFieldsMapping($customFieldsMapping);
    }
}


```

```yaml
# service definition

services:
    _defaults:
        autowire: true

    AppBundle\EventListener\FileSizeIndexSubscriber:
        tags:
            - { name: kernel.event_subscriber }
```


### Example 2: Data Objects

In this example a "User Owner" field will be provided for car documents. 
"Owner" is defined as Pimcore username of the creator of the car data object.

```php
<?php

namespace AppBundle\EventListener;

use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent;
use Pimcore\Model\DataObject\Car;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarOwnerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            UpdateIndexDataEvent::class  => 'onUpdateIndexData',
            ExtractMappingEvent::class   => 'onExtractMapping',
        ];
    }

    public function onUpdateIndexData(UpdateIndexDataEvent $event)
    {
        $car = $event->getDataObject();
        if(!$car instanceof Car) {
            return;
        }

        // Ensure that you take the original array and extend it.
        $customFields = $event->getCustomFields();

        $customFields['numberOfVariants'] = count($car->getChildren() ?? []);

        $event->setCustomFields($customFields);
    }

    public function onExtractMapping(ExtractMappingEvent $event)
    {
        if($event->getClassDefinition()->getId() !== 'CAR') {
            return;
        }

        // Ensure that you take the original array and extend it.
        $customFieldsMapping = $event->getCustomFieldsMapping();

        /**
         * Take a look at the OpenSearch docs how mapping works.
         * A 'keyword' field would be best for regular select and multi select filters.
         * For full text search it is possible to define sub-fields with special OpenSearch analyzers too.
         */
        $customFieldsMapping['numberOfVariants'] = [
            'type' => 'integer'
        ];

        $event->setCustomFieldsMapping($customFieldsMapping);
    }
}

```

#### Update index mapping and data

Call the following console command as soon as the event subscriber is set up in the symfony container configuration.

```bash
./bin/console generic-data-index:update:index -r
```