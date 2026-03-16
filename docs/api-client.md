# FiQueLa API Client

The FiQueLa API Client is a PHP library for communicating with the FiQueLa API. It uses only `curl`
and has no external dependencies.

**Table of contents**:

* _1_ - [Installation](#installation)
* _2_ - [Quick Start](#quick-start)
* _3_ - [Authentication](#authentication)
* _4_ - [Files](#files)
* _5_ - [Querying](#querying)
* _6_ - [Export](#export)
* _7_ - [History](#history)
* _8_ - [Error Handling](#error-handling)
* _9_ - [Custom Transport](#custom-transport)

## Installation

The client is part of the FiQueLa library. No additional packages are required.

```php
use FQL\Client\FiQueLaClient;

$client = new FiQueLaClient('https://api.example.com');
```

## Quick Start

```php
use FQL\Client\FiQueLaClient;

$client = new FiQueLaClient('https://api.example.com');

// Authenticate
$token = $client->login('your_user', 'YourSecret123!');
$client->setToken($token->token);

// Execute a query
$result = $client->query('SELECT * FROM [xml](file.xml).channel.item WHERE price < 250');

foreach ($result->data as $row) {
    echo $row['title'] . ': ' . $row['price'] . PHP_EOL;
}

echo "Found {$result->pagination->itemCount} total items" . PHP_EOL;
echo "Query took {$result->elapsed}ms" . PHP_EOL;
```

## Authentication

The API uses JWT Bearer token authentication. Login with `user` and `secret` credentials.

### Login

```php
$token = $client->login('username', 'SecretPass123!');
$client->setToken($token->token);

// Check token expiration
if ($token->isExpired()) {
    // Re-authenticate
}

// Get expiration timestamp
$expiresAt = $token->getExpiresAt();
```

### Token Revocation

Revoke the current JWT secret and get a new token:

```php
$newToken = $client->revoke('username', 'SecretPass123!');
$client->setToken($newToken->token);
```

### Token Management

The client does not handle token storage. It is the responsibility of the calling code
to persist and reload tokens as needed.

```php
// Check if client has a token set
if ($client->hasToken()) {
    // Token is set
}

// Get the current token string
$tokenString = $client->getToken();

// Set a previously stored token
$client->setToken($storedTokenString);
```

## Files

### List Files

```php
$files = $client->listFiles();

foreach ($files as $file) {
    echo "{$file->uuid}: {$file->name} ({$file->type}, {$file->count} rows)" . PHP_EOL;
}
```

### Get File Details

```php
$file = $client->getFile('12345678-1234-5678-1234-123456789012');

echo "Name: {$file->name}" . PHP_EOL;
echo "Type: {$file->type}" . PHP_EOL;
echo "Size: {$file->size}" . PHP_EOL;
echo "Columns:" . PHP_EOL;

foreach ($file->columns as $column) {
    echo "  - {$column->column} (dominant: {$column->dominant}, confidence: {$column->confidence})" . PHP_EOL;
}
```

### Update File Settings

```php
$updated = $client->updateFile(
    '12345678-1234-5678-1234-123456789012',
    encoding: 'windows-1250',
    query: 'channel.items'
);
```

### Delete File

```php
$deleted = $client->deleteFile('12345678-1234-5678-1234-123456789012');
```

## Querying

### Basic Query

```php
$result = $client->query('SELECT title, price FROM channel.item WHERE price < 250');
```

### Query with Pagination

```php
$result = $client->query(
    query: 'SELECT * FROM channel.item',
    limit: 10,
    page: 1
);

echo "Page {$result->pagination->page}/{$result->pagination->pageCount}" . PHP_EOL;
echo "Total items: {$result->pagination->itemCount}" . PHP_EOL;
echo "Items per page: {$result->pagination->itemsPerPage}" . PHP_EOL;
```

### Query with Cache Bypass

```php
$result = $client->query(
    query: 'SELECT * FROM channel.item',
    refresh: true
);
```

### Working with Results

```php
$result = $client->query('SELECT title, price FROM channel.item');

// Check if empty
if ($result->isEmpty()) {
    echo "No results" . PHP_EOL;
}

// Get column headers
$headers = $result->getHeaders(); // ['title', 'price']

// Access data
foreach ($result->data as $row) {
    echo $row['title'] . PHP_EOL;
}

// Access the hash for export
echo "Export hash: {$result->hash}" . PHP_EOL;
```

## Export

Export full query results by hash (obtained from a previous query).

```php
// First execute a query
$result = $client->query('SELECT * FROM channel.item');

// Export as JSON
$jsonData = $client->export($result->hash, 'json');

// Export as CSV
$csvData = $client->export($result->hash, 'csv');

// Export as TSV
$tsvData = $client->export($result->hash, 'tsv');

// Export as NDJSON
$ndjsonData = $client->export($result->hash, 'ndjson');

// CSV with custom delimiter
$csvData = $client->export($result->hash, 'csv', ';');
```

## History

### Get All History

```php
$history = $client->getHistory();

foreach ($history as $entry) {
    echo "[{$entry->createdAt}] {$entry->query}" . PHP_EOL;
    echo "  Executed: {$entry->runs}" . PHP_EOL;
}
```

### Get History by Date

```php
$history = $client->getHistory('2025-01-15');
```

## Health Check

```php
if ($client->ping()) {
    echo "API is reachable" . PHP_EOL;
}
```

## Error Handling

The client throws specific exceptions for different error types:

```php
use FQL\Client\Exception\AuthenticationException;
use FQL\Client\Exception\NotFoundException;
use FQL\Client\Exception\ValidationException;
use FQL\Client\Exception\ServerException;
use FQL\Client\Exception\ClientException;

try {
    $result = $client->query('SELECT * FROM items');
} catch (AuthenticationException $e) {
    // 401 - Invalid or expired token
    echo "Auth error: " . $e->getMessage();
} catch (NotFoundException $e) {
    // 404 - File or resource not found
    echo "Not found: " . $e->getMessage();
} catch (ValidationException $e) {
    // 422 - Invalid request
    echo "Validation error: " . $e->getMessage();
    $errors = $e->getErrors();
} catch (ServerException $e) {
    // 500 - Server error
    echo "Server error: " . $e->getMessage();
} catch (ClientException $e) {
    // Other client errors (connection, cURL issues, etc.)
    echo "Client error: " . $e->getMessage();
}
```

## Custom Transport

The client uses an `HttpTransport` interface for HTTP communication. By default, it uses
`CurlTransport`. You can provide a custom transport for testing or other purposes:

```php
use FQL\Client\HttpTransport;
use FQL\Client\Response;
use FQL\Client\FiQueLaClient;

class MyCustomTransport implements HttpTransport
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        // Custom HTTP implementation
    }
}

$client = new FiQueLaClient('https://api.example.com', null, new MyCustomTransport());
```

## Next steps

- [FiQueLa CLI](fiquela-cli.md)
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)

Or go back to [README.md](../README.md).
