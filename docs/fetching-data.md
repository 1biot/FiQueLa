# Fetching data

Executing a query does not immediately execute it; instead, it prepares the query for lazy loading. The `execute()` method
simply returns an `IteratorAggregate`, leaving it up to you to decide how to process the results.

```php
// get the results
$results = $query->execute();
```

- [Results provider](#results-provider)
- [Results methods](#results-methods)
- [Mapping - Data Transfer Objects](#mapping---data-transfer-objects)

## Results provider

`execute()` method returns the `FQL\Results\ResultsProvider` object, which can be used to fetch data. This method accepts
a parameter to specify the fetching mode—either `Results\Stream` or `Results\InMemory`. By default, the parameter is `null`,
and the library will automatically select the most suitable option it is affected by sql query itself.

```php
use FQL\Results;

$results = $query->execute(Results\InMemory::class);
$results = $query->execute(Results\Stream::class);
```

## Results methods


**getIterator():**`\Traversable`

Method to fetch all records from the results. It returns a generator with rows.

```php
$results = $query->execute();
foreach ($results->getIterator() as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

**fetchAll(**_class-string_ `$dto = null`**):**`\Generator`

It is `getIterator()` method than can be used to iterate over the results and applies a DTO if needed.

```php 
$results->fetchAll();
```

**fetch(**_class-string_ `$dto = null`**):**`?mixed`

Method to fetch first record and applies a DTO if needed.

```php
$user = $results->fetch();
```

**fetchSingle(**_string_`$field`**):**`mixed`

Method to fetch single field from first record.

```php
$name = $results->fetchSingle('name');
```

**fetchNth(**_int_ | _string_`$n`**,** _class-string_ `$dto = null`**):**`\Generator`

When `$n` is an integer then it fetches every `nth` record. When `$n` is string (`even` or `odd`) then it fetches every even or odd record and applies a DTO if needed.

```php
$fourthUsers = $results->fetchNth(4);
// or
$evenUsers = $results->fetchNth('even');
$oddUsers = $results->fetchNth('odd');
```

**exists():**`bool`

Method to check if any record exists. It is more efficient than `count()` method because it tries to fetch first record
only, and return true if it exists.

```php
if ($results->exists()) {
    echo "There are some records.\n";
}
```

**count():**`int`

Method to count records.

```php
$count = $results->count();
```

**sum(**_string_`$field`**):**`float`

Method to sum values by field.

```php
$sum = $results->sum('total_price');
```

**avg(**_string_`$field`**,** _int_`$decimalPlaces = 2` **):**`float`

Method to calculate average value by field. You can specify a number of decimal places.

```php
$avg = $results->avg('total_price', 2);
```

**max(**_string_`$field`**):**`float`

Method to get maximum value.

```php
$max = $results->max('total_price);
```

**min(**_string_`$field`**):**`float`

Method to get minimum value.

```php
$min = $results->min('total_price');
```

## Mapping - Data Transfer Objects

You can map your results to Data Transfer Objects (**DTO**) with `$dto` property when using fetch functions.

Example with anonymous DTO object:

```php
use FQL\Enum\Operator;
use FQL\Query\Debugger;

$query = $this->json->query()
    ->select('id, name, price')
    ->select('brand.name')->as('brand')
    ->select('categories[]->name')->as('categories')
    ->select('categories[]->id')->as('categoryIds')
    ->from('data.products')
    ->where('price', Operator::GREATER_THAN, 200)
    ->orderBy('price')->desc();

$dto = new class {
    public int $id;
    public string $name;
    public int $price;
    public string $brand;
    public array $categories;
    public array $categoryIds;
};

Debugger::dump($query->execute()->fetch($dto::class));
```

Output will look like this:

```
class@anonymous #753
   id: 4
   name: 'Product D'
   price: 400
   brand: 'Brand B'
   categories: array (2)
   |  0 => 'Category D'
   |  1 => 'Category E'
   categoryIds: array (2)
   |  0 => 'CAT-D'
   |  1 => 'CAT-E'
```

You can use standard classes as DTO as well:

```php
class ProductDto
{
    public int $id;
    public string $name;
    public int $price;
    public string $brand;
    public array $categories;
    public array $categoryIds;
}

class CategoryDto implements \Stringable
{
    public function __construct(
        public readonly string $name,
        public readonly string $id
    ) {
    }
    
    public function __toString() : string
    {
        return sprintf('%s-%s', $this->id, $this->name);  
    }
}

```

## Next steps

- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- Fetching Data

or go back to [README.md](../README.md).
