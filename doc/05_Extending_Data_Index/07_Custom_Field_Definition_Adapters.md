# Custom Field Definition Adapters

When adding a custom data object field type (e.g. via a bundle), the
Generic Data Index needs to know how to index the field's data. This
is done by registering a field definition adapter.

## Reusing Existing Adapters

For simple field types that store string data, you can reuse the
built-in `TextKeywordAdapter`. Register it as a service with the
`pimcore.generic_data_index.data-object.search_index_field_definition`
tag and your field type name:

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

The `type` attribute must match the value returned by your field
definition's `getFieldType()` method. The `shared: false` setting
is required because adapters are stateful (each gets a field
definition set on it).

Available built-in adapters:

- `TextKeywordAdapter` — for string/text fields (used by `input`,
  `textarea`, `select`, `multiselect`, `wysiwyg`, etc.)
- `NumericAdapter` — for numeric fields
- `DateAdapter` — for date fields
- `BooleanAdapter` — for boolean fields

See all adapters in
`vendor/pimcore/generic-data-index-bundle/config/services/search/data-object/field-definition-adapters.yml`.

## Creating Custom Adapters

For field types that need custom indexing logic, create a class
extending `AbstractAdapter`:

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

Register it the same way with the service tag.

## Rebuilding the Index

After registering a new adapter, rebuild the search index:

```bash
bin/console generic-data-index:update:index -c CLASS_ID -r
```

Replace `CLASS_ID` with your class definition ID (e.g. `7` for the
Demo class).

## Reference

For a working example, see the
[studio-example-bundle](https://github.com/pimcore/studio-example-bundle)
`simpleText` custom datatype.
