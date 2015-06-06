# Guzzle handler used to cache responses

[![Build Status](https://img.shields.io/travis/rtheunissen/guzzle-cache-handler.svg?style=flat-square&branch=master)](https://travis-ci.org/rtheunissen/guzzle-cache-handler)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-cache-handler/)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-cache-handler/)
[![Latest Version](https://img.shields.io/packagist/v/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-cache-handler)
[![License](https://img.shields.io/packagist/l/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-cache-handler)

## Installation

```bash
composer require rtheunissen/guzzle-cache-handler
```

## Usage
Provide an instance of `Doctrine\Common\Cache\CacheProvider` to cache the response. `ApcCache` is used as the default if
not provided.

Provide a Guzzle handler to use when the cache is not valid. `GuzzleHttp\choose_handler` is used as the default if
not provided.

```php
$handler = new \Concat\Http\Handler\CacheHandler($cacheProvider, $defaultHandler, [

    // HTTP methods that should be cached
    'methods' => ['GET', 'HEAD', 'OPTIONS'],

    // Time in seconds to cache the response for
    'expire' => 60,

    // Accepts a request and returns true if it should be cached
    'filter' => null,
]);


$client = new Client([
    'handler' => $handler
]);
```
