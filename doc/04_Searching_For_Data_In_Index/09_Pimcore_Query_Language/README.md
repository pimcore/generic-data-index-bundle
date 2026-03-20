# Pimcore Query Language (PQL)

Pimcore Query Language (PQL) defines a query syntax for searching data objects, assets,
and documents in the Generic Data Index.

## Syntax

```
CONDITION = EXPRESSION | CONDITION ("AND" | "OR") CONDITION
EXPRESSION = "(" CONDITION ")" | COMPARISON | QUERY_STRING_QUERY
COMPARISON = FIELDNAME OPERATOR VALUE | RELATION_COMPARISON
RELATION_COMPARISON = RELATION_FIELD_NAME OPERATOR VALUE
FIELDNAME = IDENTIFIER{.IDENTIFIER}
RELATION_FIELD_NAME = FIELDNAME:ENTITYNAME.FIELDNAME
IDENTIFIER = [a-zA-Z_]\w*
ENTITYNAME = [a-zA-Z_]\w*
OPERATOR = "="|"!="|"<"|">"|">="|"<="|"LIKE"|"NOT LIKE"
NULL = "NULL"
EMPTY = "EMPTY"
VALUE = INTEGER | FLOAT | "'" STRING "'" | '"' STRING '"' | NULL | EMPTY
QUERY_STRING_QUERY = "QUERY('" STRING "')"
```

### Operators

| Operator | Description | Examples |
|----------|-------------|----------|
| `=` | Equal (case-sensitive) | `field = "value"` |
| `!=` | Not equal (case-sensitive) | `field != "value"` |
| `<` | Less than | `field < 100` |
| `<=` | Less than or equal to | `field <= 100` |
| `>=` | Greater than or equal to | `field >= 100` |
| `>` | Greater than | `field > 100` |
| `LIKE` | Equal with wildcard support (case-insensitive). `*` matches zero or more characters, `?` matches one. | `field like "val*"` |
| `NOT LIKE` | Not equal with wildcard support (case-insensitive). `*` matches zero or more characters, `?` matches one. | `field not like "val*"` |

### Null/Empty Values

Use the `NULL` and `EMPTY` keywords with `=` and `!=` operators to search for
fields without values. `NULL` matches null values; `EMPTY` matches both null
and empty strings.

```
field = NULL
field != NULL
field = EMPTY       # equivalent to: field = NULL OR field = ''
field != EMPTY      # equivalent to: field != NULL AND field != ''
```

### AND / OR / Brackets

Combine conditions with `AND` and `OR` operators. Use brackets to group conditions:

```
field1 = "value1" AND field2 = "value2"
field1 = "value1" AND (field2 = "value2" OR field3 = "value3")
(field1 = "value1" AND (field2 = "value2" OR field3 = "value3")) OR field4 = "value4"
```

### Relation Filters

Filter along relations using the notation
`<RELATION_FIELD_NAME>:<ENTITY_NAME>.<FIELD_NAME>`:

```
main_image:Asset.type
category:Category.name
manufacturer:Company.country
```

The entity name must be `Asset`, `Document`, or a data object class name.

### Field Names

Field names match the search index structure. Nested fields use dot (`.`) notation.
Fields are organized into three sections (`system_fields`, `standard_fields`,
`custom_fields`) as described in [Extending Search Index](../../05_Extending_Data_Index/06_Extend_Search_Index.md).
Depending on the data type, attribute values may contain nested sub-attributes.

**Full path examples:**

```
system_fields.id
standard_fields.name
standard_fields.my_relation_field.asset
standard_fields.description.de
```

PQL automatically resolves short field names to their full indexed path.
This works for all unambiguous field names. If a field name exists in multiple sections
(e.g. both `system_fields` and `standard_fields`), use the full path.
Query string queries (`QUERY()`) also require full paths.

Use the technical field name as defined in the data object class or asset metadata:

```
id
name
my_relation_field
description.de
```

Access localized fields with `field_name.locale` (e.g. `description.de`).

### Query String Queries

Pass
[OpenSearch](https://opensearch.org/docs/latest/query-dsl/full-text/query-string)
or
[Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html)
query string queries directly to the index using `QUERY()`:

```
QUERY("standard_fields.color:(red OR blue)")
```

See the
[OpenSearch query string syntax](https://opensearch.org/docs/latest/query-dsl/full-text/query-string/#query-string-syntax)
or
[Elasticsearch query string syntax](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html#query-string-syntax)
for details.

:::caution

Automatic field name detection is not supported for query string queries.
Use full field paths.

:::

### Example PQL Queries

All examples reference the `Car` data object class from the
[Pimcore Demo](https://pimcore.com/en/try).

| Query | Description |
|-------|-------------|
| `series = "E-Type" AND (color = "green" OR productionYear < 1965)` | All E-Type models that are green or produced before 1965 |
| `manufacturer:Manufacturer.name = "Alfa" AND productionYear > 1965` | All Alfa cars produced after 1965 |
| `genericImages:Asset.fullPath LIKE "/Car Images/vw/*"` | All cars with images in the `/Car Images/vw` asset folder |
| `color = "red" OR color = "blue"` | All red or blue cars |
| `series = empty AND color="red"` | All models with empty series and red color |
| `License.expiryDate <= 'now+2d/d'` | All elements whose license expires within two days (uses [date math](https://www.elastic.co/guide/en/elasticsearch/reference/current/common-options.html#date-math) syntax) |
| `Query("standard_fields.color:(red OR blue)")` | All red or blue cars using query string syntax |

## Limitations

- **Relation sub-query limit:** Maximum 65,000 results for related element sub-queries.
  See `terms query` docs for
  [OpenSearch](https://opensearch.org/docs/latest/query-dsl/term/terms/) /
  [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html).
- **Asset metadata filtering:** Only predefined asset metadata and metadata from the
  asset metadata class definitions bundle are supported. Custom metadata defined
  directly on individual assets is not filterable.
- **Reserved keywords:** `AND`, `OR`, `LIKE`, `NOT LIKE`, `NULL`, `EMPTY` cannot
  be used as field names.

## Further Reading

- [Use PQL as a Developer](./03_Use_PQL_as_Developer.md)
