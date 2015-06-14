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
This is a handler which caches responses for a given amount of time. You will need
an implemented [CacheProvider](https://github.com/doctrine/cache/tree/master/lib/Doctrine/Common/Cache) and a callable [handler](https://github.com/guzzle/guzzle/tree/master/src/Handler). You also have the option
of setting a [Logger](https://github.com/php-fig/log) such as [Monolog](https://github.com/Seldaek/monolog), which will log store and fetch events.

Default implementations are [ApcCache](https://github.com/doctrine/cache/blob/master/lib/Doctrine/Common/Cache/ApcCache.php) and [Guzzle\choose_handler](https://github.com/guzzle/guzzle/blob/master/src/functions.php#L103).

```php
use Concat\Http\Handler\CacheHandler;

// Create a cache handler with a given cache provider and default handler.
$handler = new CacheHandler($cacheProvider, $defaultHandler, [

    /**
     * @var array HTTP methods that should be cached.
     */
    'methods' => ['GET', 'HEAD', 'OPTIONS'],

    /**
     * @var integer Time in seconds to cache a response for.
     */
    'expire' => 60,

    /**
     * @var callable Accepts a request and returns true if it should be cached.
     */
    'filter' => null,
]);

// Use a PSR-3 compliant logger to log when bundles are stored or fetched.
$handler->setLogger($logger);

// Create a Guzzle 6 client, passing the cache handler as 'handler'.
$client = new Client([
    'handler' => $handler
]);
```
