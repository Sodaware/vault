# VAULT - A Simple PHP Cache Framework

vault is is very lightweight cache framework that is designed to be easily
integrated with other frameworks (such as Tonic). It's only one file, and
currently supports APC caching. Future versions will include support for
memcached and flat files.

## Using vault

```php
include 'vault.php';

// Setup vault with APC
Vault::setup('APC');

// Store something
Vault::store('my_key', 'hello, world');

// Output it
echo Vault::fetch('my_key');

// Clear the cache
Vault::clear();

```

## Supported cache types

The following cache types are currently supported:

* *APC* - requires APC extension
* *Volatile* - fallback in case no other cache methods work
* *File* - Store cache values in files
