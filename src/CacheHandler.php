<?php

namespace Concat\Http\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Doctrine\Common\Cache\CacheProvider;

use GuzzleHttp\Promise\FulfilledPromise;

class CacheHandler
{

    /**
     *
     *
     *
     */
    public function __construct(
        CacheProvider $cache, callable $handler, array $options = [])
    {
        $this->cache   = $cache;
        $this->handler = $handler;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     *
     *
     */
    protected function getDefaultOptions()
    {
        return [
            'methods' => ['GET', 'HEAD', 'OPTIONS'],
            'ttl'     => 0,
        ];
    }

    /**
     *
     *
     *
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if ($this->shouldCache($request)) {
            return $this->cache($request, $options);
        }

        return $this->handler->__invoke($request, $options);
    }

    /**
     *
     *
     *
     */
    protected function getKey(RequestInterface $request, array $options)
    {
        return join(":", [
            $request->getMethod(),
            $request->getUri(),
        ]);
    }


    private function promiseToStore($key)
    {
        $ttl = $this->options['ttl'];

        return function(ResponseInterface $response) use ($key, $ttl) {
            $value = [
                'response' => 'response',
                'expires'  => time() + $ttl,
            ];

            $this->cache->save($key, $value, $ttl);
            return $response;
        };
    }

    /**
     *
     *
     *
     */
    private function store(RequestInterface $request, array $options, $key)
    {
        $response = $this->handler->__invoke($request, $options);

        if ($this->options['ttl'] > 0) {
            return $response->then($this->promiseToStore($key));
        }

        return $response;
    }

    private function doFetch($key)
    {
        $bundle = $this->cache->fetch($key);

        // Check if the bundle has expired
        if (time() < $bundle['expires']) {
            return $bundle['response'];
        }

        // Delete the expired cache entry
        $this->cache->delete($key);
    }

    /**
     *
     *
     *
     */
    private function fetch($key)
    {
        if ($this->contains($key)) {
            return $this->doFetch($key);
        }
    }

    /**
     *
     *
     *
     */
    private function shouldCache(RequestInterface $request)
    {
        $methods = $this->options['methods'];

        //
        if (is_array($this->options['methods'])) {
            return in_array($request->getMethod(), $methods);
        }
    }

    /**
     *
     *
     *
     */
    private function contains($key)
    {
        return $this->cache->contains($key);
    }

    /**
     *
     *
     *
     */
    private function cache(RequestInterface $request, array $options)
    {
        $key = $this->getKey($request, $options);

        $response = $this->fetch($key);

        if ( ! is_null($response)) {
            return new FulfilledPromise($response);
        }

        return $this->store($request, $options, $key);
    }
}
