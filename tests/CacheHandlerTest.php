<?php

namespace Concat\Http\Handler\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Cache\ArrayCache;
use Concat\Http\Handler\CacheHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Mockery as m;

class CacheHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    private function getFunction($class, $name)
    {
        $class = new \ReflectionClass($class);
        $function = $class->getMethod($name);
        $function->setAccessible(true);
        return $function;
    }

    public function providerTestFilter()
    {
        return [
            [true, true],
            [true, false],
            [true, null],
            [true, function ($request) { return true; }],
            [false, function ($request) { return false; }],
        ];
    }
    /**
     * @dataProvider providerTestFilter
     */
    public function testFilter($expected, $filter)
    {
        $function = $this->getFunction(CacheHandler::class, 'filter');
        $handler = new CacheHandler();
        $handler->setOptions(['filter' => $filter]);
        $request = m::mock(RequestInterface::class);
        $this->assertEquals($expected, $function->invoke($handler, $request, $filter));
    }

    public function providerTestCheckMethod()
    {
        return [
            [true, 'GET', ['GET']],
            [true, 'GET', ['GET', 'POST']],
            [false, 'GET', ['HEAD']],
        ];
    }

    /**
     * @dataProvider providerTestCheckMethod
     */
    public function testCheckMethod($expected, $method, $methods)
    {
        $function = $this->getFunction(CacheHandler::class, 'checkMethod');
        $handler = new CacheHandler();
        $handler->setOptions(['methods' => $methods]);

        $request = m::mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn($method);

        $this->assertEquals($expected, $function->invoke($handler, $request));
    }

    public function testRequestShouldStore()
    {
        $response = m::mock(ResponseInterface::class);

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 10,
        ]);

        $client = new Client(['handler' => $handler]);

        $key = 'GET:/:2e0ec6d556792df9bf25a1b3fd097058';

        $client->get('/');
        $this->assertTrue($cache->contains($key));
        $client->get('/');
    }

    public function testCacheShouldDeleteIfExpired()
    {
        $response = m::mock(ResponseInterface::class);

        $cache = m::mock(ArrayCache::class . "[fetch,delete]");
        $cache->shouldReceive('fetch')->times(1)->andReturn([
            'response' => $response,
            'expires' => time() - 1000,
        ]);
        $cache->shouldReceive('delete')->times(1);

        $mockHandler = new MockHandler([
            $response,
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 1,
        ]);

        $client = new Client(['handler' => $handler]);

        $key = 'GET:/:2e0ec6d556792df9bf25a1b3fd097058';

        $this->assertFalse($cache->contains($key));
        $client->get('/');
        $this->assertTrue($cache->contains($key));
        $client->get('/');
        $this->assertTrue($cache->contains($key));
    }

    public function testZeroExpireShouldNotStore()
    {
        $response = m::mock(ResponseInterface::class);

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 0,
        ]);

        $client = new Client(['handler' => $handler]);

        $key = 'GET:/:2e0ec6d556792df9bf25a1b3fd097058';

        $client->get('/');
        $this->assertFalse($cache->contains($key));
        $client->get('/');
    }

    public function testShouldNotCache()
    {
        $response = m::mock(ResponseInterface::class);

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 0,
            'methods' => ["PUT"],
        ]);

        $client = new Client(['handler' => $handler]);

        $key = 'GET:/:2e0ec6d556792df9bf25a1b3fd097058';

        $client->get('/');
        $this->assertFalse($cache->contains($key));
    }

    public function testLogger()
    {
        $response = m::mock(ResponseInterface::class);

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 10,
        ]);

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('log')->times(2)->with(m::type('string'), m::type('string'), m::type('array'));

        $handler->setLogger($logger);

        $client = new Client(['handler' => $handler]);

        $client->get('/');
        $client->get('/');
    }
}
