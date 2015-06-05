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
            'expire' => 0,
            'methods' => ['GET'],
        ];
        $options = array_merge($defaults, $options);

        $cache = new ArrayCache();
        $defaultHandler = new MockHandler([new Response(200, [])]);
        $cacheHandler = new CacheHandler($cache, $defaultHandler, $options);
        $client = new Client(['handler' => $cacheHandler]);

        return compact('cache', 'client');
    }

    public function testFetch()
    {
        extract($this->create([
            'expire' => 60,
        ]));

        $client->get('/');
        $this->assertTrue($cache->contains('GET:/'));

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
        $this->assertTrue($cache->contains('GET:/'));

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
        $this->assertFalse($cache->contains('GET:/'));
    }

    public function testBadMethod()
    {
        extract($this->create([
            'methods' => [],
        ]));

        $client->get('/');
        $this->assertFalse($cache->contains('GET:/'));
    }
}
