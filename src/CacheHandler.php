<?php

namespace Concat\Http\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ApcCache;
use GuzzleHttp\Promise\FulfilledPromise;

/**
 * Guzzle handler used to cache responses using Doctrine\Common\Cache.
 */
class CacheHandler
{

    /**
     * @var \Doctrine\Common\Cache\CacheProvider Cache provider.
     */
    private $provider;

    /**
     * @var callable Default handler used to send response.
     */
    private $handler;

    /**
     * @var array Configuration options.
     */
    private $options;

    /**
     * Constructs a new cache handler.
     *
     * @param \Doctrine\Common\Cache\CacheProvider $provider Cache provider.
     * @param callable $handler Default handler used to send response.
     * @param array $options Configuration options.
     */
    public function __construct(
        CacheProvider $provider = null,
        callable $handler = null,
        array $options = []
    ) {

        // Set the cache provider used to cached the response.
        $this->provider = $provider ?: $this->getDefaultCacheProvider();

        // Set the handler used to send requests.
        $this->handler  = $handler ?: $this->getDefaultHandler();

        // Override default options
        $this->options  = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Returns the default cache provider, used if a cache provider is not set.
     *
     * @return \Doctrine\Common\Cache\ApcCache
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
     * @return \GuzzleHttp\Promise\PromiseInterface
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
     * @param \Psr\Http\Message\RequestInterface The request to filter.
     *
     * @return boolean true if should be cached, false otherwise.
     */
    private function filter(RequestInterface $request)
    {
        $filter = $this->options['filter'];
        return ! is_callable($filter) || $filter($request);
    }

    /**
     * Checks the method of the request to determine if it should be cached.
     *
     * @param \Psr\Http\Message\RequestInterface The request to filter.
     *
     * @return boolean true if should be cached, false otherwise.
     */
    private function checkMethod(RequestInterface $request)
    {
        $methods = (array) $this->options['methods'];
        return in_array($request->getMethod(), $methods);
    }

    /**
     * Returns true if the given request should be cached.
     *
     * @param \Psr\Http\Message\RequestInterface $request The request to check.
     *
     * @return boolean true if the request should be cached, false otherwise.
     */
    private function shouldCache(RequestInterface $request)
    {
        return $this->checkMethod($request) && $this->filter($request);
    }

    /**
     * Attempts to fetch the response from the cache, otherwise returns a
     * promise to store the response produced by the default handler.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function cache(RequestInterface $request, array $options)
    {
        $key = $this->generateKey($request, $options);

        if ($response = $this->fetch($key)) {
            return new FulfilledPromise($response);
        }

        return $this->store($request, $options, $key);
    }

    /**
     * Generates the cache key for the given request and request options. The
     * namespace should be set on the cache provider.
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
     * Returns a function that stores the response.
     *
     * @param string $key The key to store the response to.
     *
     * @return \Closure Function that stores the response.
     */
    private function doStore($key)
    {
        return function (ResponseInterface $response) use ($key) {
            $value = [
                'response' => 'response',
                'expires'  => time() + $this->options['expire'],
            ];

            $this->provider->save($key, $value, $this->options['expire']);
            return $response;
        };
    }

    /**
     * Uses the default handler to send the request, then promises to store the
     * response. Only stores if 'ttl' is greater than 0.
     *
     * @param string $key The key to store the response to.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function store(RequestInterface $request, array $options, $key)
    {
        $response = call_user_func($this->handler, $request, $options);

        if ($this->options['expire'] > 0) {
            return $response->then($this->doStore($key));
        }

        return $response;
    }

    /**
     * Fetches a response from the cache for a given key, null if invalid.
     *
     * @param string $key The key to fetch.
     *
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    private function doFetch($key)
    {
        $bundle = $this->provider->fetch($key);

        if (time() < $bundle['expires']) {
            return $bundle['response'];
        }

        $this->provider->delete($key);
    }

    /**
     * Checks if the cache containers the given key, then fetched it.
     *
     * @param string $key The key to fetch.
     *
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    private function fetch($key)
    {
        if ($this->provider->contains($key)) {
            return $this->doFetch($key);
        }
    }
}
