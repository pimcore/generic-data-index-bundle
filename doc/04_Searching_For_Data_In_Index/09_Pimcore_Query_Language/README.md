# Pimcore Query Language

Pimcore Query Language (PQL) is a query language that allows you to search for data in the Pimcore Generic Data Index. It is a simple and powerful query language that allows you to search for data using a wide range of search criteria.

## Syntax

Description of the PQL syntax:

```
CONDITION = EXPRESSION | CONDITION ("AND" | "OR") CONDITION
EXPRESSION = "(" CONDITION ")" | COMPARISON | QUERY_STRING_QUERY
COMPARISON = FIELDNAME OPERATOR VALUE | RELATION_COMPARISON
RELATION_COMPARISON = RELATION_FIELD_NAME OPERATOR VALUE
FIELDNAME = IDENTIFIER{.IDENTIFIER}                         
RELATION_FIELD_NAME = FIELDNAME:ENTITYNAME.FIELDNAME      
IDENTIFIER = [a-zA-Z_]\w*
ENTITYNAME = [a-zA-Z_]\w*
OPERATOR = "="|"<"|">"|">="|"<="|"LIKE"
VALUE = INTEGER | FLOAT | "'" STRING "'" | '"' STRING '"'
QUERY_STRING_QUERY = "QUERY('" STRING "')"
```

### Operators

| Operator | Description                                                                                                  | Examples                               |
|----------|--------------------------------------------------------------------------------------------------------------|----------------------------------------|
| `=`        | equal                                                                                                        | `field = "value"`                        |
| `<`        | smaller than                                                                                                 | `field < 100`                            |
| `<=`       | smaller or equal than                                                                                        | `field <= 100`                           |
| `=>`       | bigger or equal than                                                                                         | `field >= 100`                           |
| `>`        | bigger than                                                                                                  | `field > 100`                            |
| `LIKE`     | equal with wildcard support<br><em>* matches zero or more characters<br> ? matches any single character</em> | `field like "val*"<br>field like "val?e"` |

### AND / OR / Brackets

You can combine multiple conditions using the `AND` and `OR` operators. You can also use brackets to group conditions.

**Examples:**

```
field1 = "value1" AND field2 = "value2"
field1 = "value1" AND (field2 = "value2" OR field3 = "value3")
(field1 = "value1" AND (field2 = "value2" OR field3 = "value3")) or field4 = "value4"
```


### Relation Filters

Supports filtering along relations with following notation:

`<RELATION_FIELD_NAME>:<ENTITY_NAME>.<FIELD_NAME>`

**Examples:**

```
main_image:Asset.type
category:Category.name
manufacturer:Company.country
```


### Field Names

The field names are named and structured the same way like in the OpenSearch index. Nested field names are supported with a dot ('.') notation.
As described [here](../../05_Extending_Data_Index/06_Extend_Search_Index.md) the fields are separated into three sections (system_fields, standard_fields and custom_fields) and depending on the data type of a attribute the attribute value could be a nested structure with sub-attributes.


**Examples for field names with their full path in the index:**

```
system_fields.id
standard_fields.name
standard_fields.my_relation_field.asset
```

To simplify the usage of the PQL the field names can be used without the full path in most of the cases. The PQL will automatically search in the index structure and try to detect the correct field. So normally it's enough to use the technical field name like used for example in the data object class or asset metadata attribute.

**Above examples for field names without the full path:**

```
id
name
my_relation_field
```

The entity name can be either 'Asset', 'Document' or the name of the data object class.

### Query String Query Filters

The PQL allows passing OpenSearch [query string queries](https://opensearch.org/docs/latest/query-dsl/full-text/query-string/#query-string-syntax) directly to the index. The query string query syntax provides even more flexibility to define the search criteria. Take a look at the [OpenSearch documentation](https://opensearch.org/docs/latest/query-dsl/full-text/query-string/#query-string-syntax) for more details.

**Caution**: The automatic field detection is not supported for query string queries. So you have to use the full path for the field names.

### Example PQL Queries

All examples are based on the `Car` data object class of the [Pimcore Demo](https://pimcore.com/en/try).

| Query                                                               | Description                                                                                                               | 
|---------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| `series = "E-Type" AND (color = "green" OR productionYear < 1965)`  | All E-Type models which are green or produced before 1965.                                                                |
| `manufacturer:Manufacturer.name = "Alfa" and productionYear > 1965` | All Alfa cars produced after 1965.                                                                                        |
| `genericImages:Asset.fullPath like "/Car Images/vw/*"`              | All cars with a image linked in the `genericImages` image gallery which is contained in the asset folder `/Car Images/vw`. |
| `color = "red" or color = "blue"`                                   | All red or blue cars using standard PQL syntax.                                                                           |
| `Query("standard_fields.color:(red or blue)")`                      | All red or blue cars using simple query string syntax.                                                                    |


## Limitations

* When searching for related elements the maximum possible results amount of sub queries is 65.000, see also [terms query documentation](https://opensearch.org/docs/latest/query-dsl/term/terms/).
* Filtering for asset metadata fields is only possible if they are defined as predefined asset metadata or via the asset metadata class definitions bundle. Custom asset metadata fields directly defined on single assets are not supported.

## Further Reading

- [Use PQL as a Developer](./03_Use_PQL_as_Developer.md).
