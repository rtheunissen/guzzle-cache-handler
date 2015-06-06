<?php

namespace Concat\Http\Handler\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\ArrayCache;

class CacheHandlerTest extends \PHPUnit_Framework_TestCase
{

    private function create(array $options)
    {
        $defaults = [
            'responses' => 1,
            'expire' => 60,
            'methods' => ['GET'],
        ];
        $options = array_merge($defaults, $options);

        $cache = new ArrayCache();
        $defaultHandler = new MockHandler([new Response(200, [])]);
        $cacheHandler = new CacheHandler($cache, $defaultHandler, $options);
        $client = new Client(['handler' => $cacheHandler]);

        return compact('cache', 'client');
    }

    private function stored($cache)
    {
        return $cache->contains('GET:/:2e0ec6d556792df9bf25a1b3fd097058');
    }

    public function testFetch()
    {
        extract($this->create([
            'expire' => 60,
        ]));

        $client->get('/');
        $this->assertTrue($this->stored($cache));

        // would throw an exception if the request is made
        $client->get('/');
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testExpire()
    {
        extract($this->create([
            'expire' => 1,
        ]));

        $client->get('/');
        $this->assertTrue($this->stored($cache));

        sleep(1);

        // would throw an exception if the request is made
        $client->get('/');
    }

    public function testSkipStore()
    {
        extract($this->create([
            'expire' => 0,
        ]));

        $client->get('/');
        $this->assertFalse($this->stored($cache));
    }

    public function testBadMethod()
    {
        extract($this->create([
            'methods' => [],
        ]));

        $client->get('/');
        $this->assertFalse($this->stored($cache));
    }

    public function testFalseFilter()
    {
        extract($this->create([
            'filter' => function ($request) {
                return false;
            },
        ]));

        $client->get('/');
        $this->assertFalse($this->stored($cache));
    }

    public function testTrueFilter()
    {
        extract($this->create([
            'filter' => function ($request) {
                return true;
            },
        ]));

        $client->get('/');
        $this->assertTrue($this->stored($cache));
    }

    public function testNotCallableFilter()
    {
        extract($this->create([
            'filter' => true,
        ]));

        $client->get('/');
        $this->assertTrue($this->stored($cache));
    }
}
