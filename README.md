# Guzzle handler used to cache responses

[![Author](http://img.shields.io/badge/author-@rudi_theunissen-blue.svg?style=flat-square)](https://twitter.com/rudi_theunissen)
[![License](https://img.shields.io/packagist/l/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-cache-handler)
[![Latest Version](https://img.shields.io/packagist/v/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-cache-handler)
[![Build Status](https://img.shields.io/travis/rtheunissen/guzzle-cache-handler.svg?style=flat-square&branch=master)](https://travis-ci.org/rtheunissen/guzzle-cache-handler)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-cache-handler/)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/rtheunissen/guzzle-cache-handler.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-cache-handler/)

## Installation

```bash
composer require rtheunissen/guzzle-cache-handler
```

## Usage

This is a handler which caches responses for a given amount of time. 

You will need an implemented [CacheInterface](https://github.com/rtheunissen/cache/blob/master/src/CacheInterface.php). See [rtheunissen/cache](https://github.com/rtheunissen/cache) for more
details.


```php
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;


// Basic directory cache example
$cacheProvider = new FilesystemCache(__DIR__ . '/cache');

// Create a cache handler with a given cache provider and default handler.
$handler = new CacheHandler($cacheProvider, $handler, [

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
