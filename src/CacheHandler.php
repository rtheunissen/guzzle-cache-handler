<?php

namespace Concat\Http\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Promise\FulfilledPromise;

/**
 * Guzzle handler used to cache responses using Doctrine\Common\Cache.
 */
class CacheHandler
{

    /**
     * @var Doctrine\Common\Cache\CacheProvider Cache provider.
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
     * @param Doctrine\Common\Cache\CacheProvider $provider Cache provider.
     * @param callable $handler Default handler used to send response.
     * @param array $options Configuration options.
     *                       - methods : array of http methods to cache
     *                       - ttl     : time in seconds to cache for
     */
    public function __construct(
        CacheProvider $provider, callable $handler, array $options = [])
    {
        $this->provider = $provider;
        $this->handler  = $handler;
        $this->options  = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Returns the default confiration options.
     *
     * @return array The default configuration options.
     */
    protected function getDefaultOptions()
    {
        return [
            'methods' => ['GET', 'HEAD', 'OPTIONS'],
            'ttl'     => 60, // seconds
        ];
    }

    /**
     * Called when a request is made on the client.
     *
     * @return GuzzleHttp\Promise\Promise
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if ($this->shouldCache($request)) {
            return $this->cache($request, $options);
        }

        return $this->handler->__invoke($request, $options);
    }

    /**
     * Returns true if the given request should be cached.
     *
     * @param Psr\Http\Message\RequestInterface $request The request to check.
     *
     * @return boolean true if the request should be cached, false otherwise.
     */
    private function shouldCache(RequestInterface $request)
    {
        $methods = $this->options['methods'];
        return is_array($methods) && in_array($request->getMethod(), $methods);
    }

    /**
     * Attempts to fetch the response from the cache, otherwise returns a
     * promise to store the response produced by the default handler.
     *
     * @return GuzzleHttp\Promise\Promise
     */
    private function cache(RequestInterface $request, array $options)
    {
        $key = $this->generateKey($request, $options);

        $response = $this->fetch($key);

        if ( ! is_null($response)) {
            return new FulfilledPromise($response);
        }

        return $this->store($request, $options, $key);
    }

    /**
     * Generates the cache key for the given request and request options. The
     * namespace should be set on the cache provider instead.
     *
     * @return string The cache key
     */
    protected function generateKey(RequestInterface $request, array $options)
    {
        return join(":", [
            $request->getMethod(),
            $request->getUri(),
        ]);
    }

    /**
     * Returns a function that stores the response.
     *
     * @param string $key The key to store the response to.
     *
     * @return Closure Function that stores the response.
     */
    private function doStore($key)
    {
        return function(ResponseInterface $response) use ($key) {
            $value = [
                'response' => 'response',
                'expires'  => time() + $this->options['ttl'],
            ];

            $this->provider->save($key, $value, $this->options['ttl']);
            return $response;
        };
    }

    /**
     * Uses the default handler to send the request, then promises to store the
     * response. Only stores if 'ttl' is greater than 0.
     *
     * @return GuzzleHttp\Promise\Promise
     */
    private function store(RequestInterface $request, array $options, $key)
    {
        $response = $this->handler->__invoke($request, $options);

        if ($this->options['ttl'] > 0) {
            return $response->then($this->doStore($key));
        }

        return $response;
    }

    /**
     * Fetches a response from the cache for a given key, null if invalid.
     *
     * @param string $key The key to fetch.
     *
     * @return Psr\Http\Message\ResponseInterface|null
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
     * @return Psr\Http\Message\ResponseInterface|null
     */
    private function fetch($key)
    {
        if ($this->provider->contains($key)) {
            return $this->doFetch($key);
        }
    }
}
