<?php

namespace Concat\Http\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ApcCache;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Guzzle handler used to cache responses using Doctrine\Common\Cache.
 */
class CacheHandler
{

    /**
     * @var \Doctrine\Common\Cache\CacheProvider Cache provider.
     */
    protected $cache;

    /**
     * @var callable Default handler used to send response.
     */
    protected $handler;

    /**
     * @var \Psr\Log\LoggerInterface PSR-3 compliant logger.
     */
    protected $logger;

    /**
     * @var array Configuration options.
     */
    protected $options;

    /**
     * Constructs a new cache handler.
     *
     * @param CacheProvider $cache Cache provider.
     * @param callable $handler Default handler used to send response.
     * @param array $options Configuration options.
     */
    public function __construct(
        CacheProvider $cache = null,
        callable $handler = null,
        array $options = []
    ) {
        $cache   = $cache   ?: $this->getDefaultCacheProvider();
        $handler = $handler ?: $this->getDefaultHandler();

        $this->setCacheProvider($cache);
        $this->setHandler($handler);
        $this->setOptions($options);
    }

    /**
     * Sets the fallback handler to use when the cache is invalid.
     *
     * @param callable $handler
     */
    public function setHandler(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Sets the cache provider.
     *
     * @param CacheProvider $cache
     */
    public function setCacheProvider(CacheProvider $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Resets the options, merged with default values.
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Sets the logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns the default cache provider, used if a cache provider is not set.
     *
     * @return ApcCache
     *
     * @codeCoverageIgnore
     */
    protected function getDefaultCacheProvider()
    {
        return new ApcCache();
    }

    /**
     * Returns the default handler, used if a handler is not set.
     *
     * @return callable
     * @codeCoverageIgnore
     */
    protected function getDefaultHandler()
    {
        return \GuzzleHttp\choose_handler();
    }

    /**
     * Returns the default confiration options.
     *
     * @return array The default configuration options.
     */
    protected function getDefaultOptions()
    {
        return [

            // HTTP methods that should be cached
            'methods' => ['GET', 'HEAD', 'OPTIONS'],

            // Time in seconds to cache the response for
            'expire'  => 60,

            // Accepts a request and returns true if it should be cached
            'filter'  => null,
        ];
    }

    /**
     * Called when a request is made on the client.
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if ($this->shouldCache($request)) {
            return $this->cache($request, $options);
        }

        return call_user_func($this->handler, $request, $options);
    }

    /**
     * Filters the request using a configured filter to determine if it should
     * be cached.
     *
     * @param RequestInterface The request to filter.
     *
     * @return boolean true if should be cached, false otherwise.
     */
    protected function filter(RequestInterface $request)
    {
        $filter = $this->options['filter'];
        return ! is_callable($filter) || $filter($request);
    }

    /**
     * Checks the method of the request to determine if it should be cached.
     *
     * @param RequestInterface The request to filter.
     *
     * @return boolean true if should be cached, false otherwise.
     */
    protected function checkMethod(RequestInterface $request)
    {
        $methods = (array) $this->options['methods'];
        return in_array($request->getMethod(), $methods);
    }

    /**
     * Returns true if the given request should be cached.
     *
     * @param RequestInterface $request The request to check.
     *
     * @return boolean true if the request should be cached, false otherwise.
     */
    protected function shouldCache(RequestInterface $request)
    {
        return $this->checkMethod($request) && $this->filter($request);
    }

    /**
     * Attempts to fetch the response from the cache, otherwise returns a
     * promise to store the response produced by the default handler.
     *
     * @param RequestInterface $request The request to cache.
     * @param array $options Configuration options.
     *
     * @return PromiseInterface
     */
    protected function cache(RequestInterface $request, array $options)
    {
        $key = $this->generateKey($request, $options);

        // Attempt to fetch a response bundle from the cache
        $bundle = $this->fetch($key);

        if ($bundle) {
            $this->logFetchedBundle($request, $bundle);
            return new FulfilledPromise($bundle['response']);
        }

        return $this->store($request, $key, $options);
    }

    /**
     * Generates the cache key for the given request and request options. The
     * namespace should be set on the cache provider.
     *
     * @param RequestInterface $request The request to generate a key for.
     * @param array $options Configuration options.
     *
     * @return string The cache key
     */
    protected function generateKey(RequestInterface $request, array $options)
    {
        return join(":", [
            $request->getMethod(),
            $request->getUri(),
            md5(json_encode($options)),
        ]);
    }

    /**
     * Builds a cache bundle using a given response.
     *
     * @param ResponseInterface $response
     *
     * @return array The response bundle to cache.
     */
    protected function buildCacheBundle(ResponseInterface $response)
    {
        return [
            'response' => $response,
            'expires'  => time() + $this->options['expire'],
        ];
    }

    /**
     * Stores a given response bundle to a given key.
     *
     * @param string $key The key to store the response to.
     * @param array $bundle The response bundle.
     */
    protected function doStore($key, $bundle)
    {
        $this->cache->save($key, $bundle, $this->options['expire']);
    }

    /**
     * Uses the default handler to send the request, then promises to store the
     * response. Only stores the request if 'expire' is greater than 0.
     *
     * @param RequestInterface $request The request to store.
     * @param string $key The key to store the response to.
     * @param array $options Configuration options.
     *
     * @return PromiseInterface
     */
    protected function store(RequestInterface $request, $key, array $options)
    {
        $default = call_user_func($this->handler, $request, $options);

        if ($this->options['expire'] <= 0) {
            return $default;
        }

        return $this->promiseToStore($request, $default, $key);
    }

    /**
     * Returns a promise to store a response when it is received.
     *
     * @param RequestInterface $request The request to store.
     * @param PromiseInterface $default The default handler promise.
     * @param string $key The key to store the response to.
     *
     * @return PromiseInterface
     */
    protected function promiseToStore(
        RequestInterface $request,
        PromiseInterface $default,
        $key
    ) {
        $promise = function (ResponseInterface $response) use ($request, $key) {

            // Build the response bundle to be stored
            $bundle = $this->buildCacheBundle($response);

            $this->doStore($key, $bundle);
            $this->logStoredBundle($request, $bundle);

            return $response;
        };

        return $default->then($promise);
    }

    /**
     * Fetches a response from the cache for a given key, null if invalid.
     *
     * @param string $key The key to fetch.
     *
     * @return array|null Bundle from cache or null if expired.
     */
    protected function doFetch($key)
    {
        $bundle = $this->cache->fetch($key);

        if (time() < $bundle['expires']) {
            return $bundle;
        }

        $this->cache->delete($key);
    }

    /**
     * Checks if the cache containers the given key, then fetched it.
     *
     * @param string $key The key to fetch.
     *
     * @return array|null A response bundle or null if expired.
     */
    protected function fetch($key)
    {
        if ($this->cache->contains($key)) {
            return $this->doFetch($key);
        }
    }

    /**
     * Returns the log level to use when logging bundles.
     *
     * @return string LogLevel
     */
    protected function getLogLevel()
    {
        return LogLevel::DEBUG;
    }

    /**
     * Convenient internal logger entry point.
     */
    private function log($message, $bundle)
    {
        if (isset($this->logger)) {
            $this->logger->log($this->getLogLevel(), $message, $bundle);
        }
    }

    /**
     * Logs that a bundle has been stored in the cache.
     *
     * @param RequestInterface $request The request.
     * @param array $bundle The stored response bundle.
     */
    protected function logStoredBundle(
        RequestInterface $request,
        array $bundle
    ) {
        $this->log($this->getStoredLogMessage($request, $bundle), $bundle);
    }

    /**
     * Logs that a bundle has been fetched from the cache.
     *
     * @param RequestInterface $request The request that produced the response.
     * @param array $bundle The fetched response bundle.
     */
    protected function logFetchedBundle(
        RequestInterface $request,
        array $bundle
    ) {
        $this->log($this->getFetchedLogMessage($request, $bundle), $bundle);
    }

    /**
     * Internal abstraction for log messages.
     */
    private function getLogMessage(
        RequestInterface $request,
        array $bundle,
        $format
    ) {
        return vsprintf($format, [
            gmdate('c'),
            $request->getMethod(),
            $request->getUri(),
            $bundle['expires'] - time(),
        ]);
    }

    /**
     * Returns the log message for when a bundle is stored in the cache.
     *
     * @param RequestInterface $request The request that produced the response.
     * @param array $bundle The stored response bundle.
     *
     * @return string The log message.
     */
    protected function getStoredLogMessage(
        RequestInterface $request,
        array $bundle
    ) {
        return $this->getLogMessage(
            $request,
            $bundle,
            "[%s] %s %s stored in cache (expires in %ss)"
        );
    }

    /**
     * Returns the log message for when a bundle is fetched from the cache.
     *
     * @param RequestInterface $request The request that produced the response.
     * @param array $bundle The stored response bundle.
     *
     * @return string The log message.
     */
    protected function getFetchedLogMessage(
        RequestInterface $request,
        array $bundle
    ) {
        return $this->getLogMessage(
            $request,
            $bundle,
            "[%s] %s %s fetched from cache (expires in %ss)"
        );
    }
}
