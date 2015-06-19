<?php

namespace Concat\Http\Handler\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Cache\ArrayCache;
use Concat\Http\Handler\CacheHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
        $request = m::mock(Request::class);
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

        $request = m::mock(Request::class);
        $request->shouldReceive('getMethod')->andReturn($method);

        $this->assertEquals($expected, $function->invoke($handler, $request));
    }

    public function testRequestShouldStore()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

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
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

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

    /**
     * @expectedException RuntimeException
     */
    public function testCacheFetchException()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $cache = m::mock(ArrayCache::class . "[fetch]");
        $cache->shouldReceive('fetch')->andReturn(false);

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 1,
        ]);

        $client = new Client(['handler' => $handler]);
        $client->get('/');
        $client->get('/');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCacheStoreException()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $cache = m::mock(ArrayCache::class . "[save]");
        $cache->shouldReceive('save')->andReturn(false);

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 1,
        ]);

        $client = new Client(['handler' => $handler]);
        $client->get('/');
    }

    public function testZeroExpireShouldNotStore()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

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

    public function testShouldNotCacherRequest()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

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

    public function testShouldNotCacheResponse()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(400);

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
        $this->assertFalse($cache->contains($key));
    }

    public function providerTestLogger()
    {
        return [
            ["debug", "{event}"],
            [function() { return "debug"; }],
            [null],
        ];
    }

    /**
     * @dataProvider providerTestLogger
     */
    public function testLogger($level, $template = null)
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 10,
        ]);

        if ($template) {
            $handler->setLogTemplate($template);
        }

        if ($level) {
            $handler->setLogLevel($level);
        }

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('log')->times(1)->with(
            "debug",
            m::on(function($message){
                return strpos($message, 'stored') !== false;
            }),
            m::on(function($context){
                return isset($context['response'], $context['expires']);
            })
        );

        $logger->shouldReceive('log')->times(2)->with(
            "debug",
            m::on(function($message){
                return strpos($message, 'fetched') !== false;
            }),
            m::on(function($context){
                return isset($context['response'], $context['expires']);
            })
        );

        $handler->setLogger($logger);

        $client = new Client(['handler' => $handler]);

        $client->get('/');
        $client->get('/');
        $client->get('/');
    }
}
