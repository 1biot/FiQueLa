# Opening files and do queries
## Query from string

```php
use FQL\Enum;
use FQL\Stream;

$json = Stream\Provider::fromString(
    "{'data':[{'id':3,'name':'Product'}]}",
    Enum\Format::JSON
);

$query = $json->query()
    ->selectAll()
    ->from('data');
```

## Query from file

```php
use FQL\Enum;
use FQL\Stream;

$xml = Stream\Provider::fromFile('just/a/path/to/whatever/file', Enum\Format::XML);

// or simplified version
$xml = Stream\Provider::fromFile('just/a/path/to/whatever/file.xml');

// or direct use stream
$xml = Stream\Xml::open('just/a/path/to/whatever/file.xml');

// and then query you file
$query = $xml->query()
    ->selectAll()
    ->from('SHOP.SHOPITEM')
    ->where('EAN', Enum\Operator::EQUAL, '1234567891011')
    ->or('PRICE', Enum\Operator::LESS_THAN_OR_EQUAL, 200)
    ->orderBy('PRICE')->desc()
    ->limit(10);
```

## Query from FileQuery

Previous `$query` simplified:

```php
use FQL\Enum;
use FQL\Query;

$query = Query\Provider::fromFileQuery('(./path/to/file.xml).SHOP.SHOPITEM')
    ->selectAll()
    ->where('EAN', Enum\Operator::EQUAL, '1234567891011')
    ->or('PRICE', Enum\Operator::LESS_THAN_OR_EQUAL, 200)
    ->orderBy('PRICE')->desc()
    ->limit(10);
```

## File Query Language

It is my favorite way to query files. It is a simple SQL-like language that allows you to query files directly.
It supports all file formats and the most functionality from Fluent API.

```php
use FQL\Query;

$query = Query\Provider::fql(<<<SQL
SELECT *
FROM [xml](./products_file.tmp).SHOP.SHOPITEM
WHERE EAN = "1234567891011"
    OR PRICE <= 200
SQL
);
```

More about [File Query Language](file-query-language.md).

## Next steps

- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching data](fetching-data.md)
- [API Reference](api-reference.md)

or go back to [README.md](../README.md).

