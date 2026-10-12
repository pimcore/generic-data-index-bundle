---
title: Custom Field Definition Adapters
description: Register field definition adapters for custom data object field types in the Generic Data Index.
---

# Custom Field Definition Adapters

When adding a custom data object field type (e.g. via a bundle), the Generic Data Index
needs a field definition adapter to know how to index the field's data.

## Reusing Existing Adapters

For simple field types that store string data, reuse the built-in `TextKeywordAdapter`.
Register it as a service with the
`pimcore.generic_data_index.data-object.search_index_field_definition` tag:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    my_bundle.gdi.simple_text_adapter:
        class: Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\TextKeywordAdapter
        shared: false
        tags:
            - name: "pimcore.generic_data_index.data-object.search_index_field_definition"
              type: "simpleText"
```

The `type` attribute must match the value returned by your field definition's
`getFieldType()` method. Set `shared: false` because adapters are stateful
(each instance gets a field definition assigned).

### Available Built-in Adapters

- `TextKeywordAdapter` - string/text fields (`input`, `textarea`, `select`,
  `multiselect`, `wysiwyg`, etc.)
- `NumericAdapter` - numeric fields
- `DateAdapter` - date fields
- `BooleanAdapter` - boolean fields

See all adapters in
`config/services/search/data-object/field-definition-adapters.yml`.

## Creating Custom Adapters

For field types that need custom indexing logic, extend `AbstractAdapter`:

```php
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter;

final class MyCustomAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        // Return OpenSearch/Elasticsearch mapping for this field
        return [
            'type' => 'keyword'
        ];
    }
}
```

Register it with the same service tag as shown above.

## Rebuilding the Index

After registering a new adapter, rebuild the search index for the affected class:

```bash
bin/console generic-data-index:update:index -c CLASS_ID -r
```

Replace `CLASS_ID` with the class definition ID (e.g. `7` for the Demo class).

## Reference

For a working example, see the
[studio-example-bundle](https://github.com/pimcore/studio-example-bundle)
`simpleText` custom datatype.
