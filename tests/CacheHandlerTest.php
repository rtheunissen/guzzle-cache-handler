<?php

namespace Concat\Http\Handler\Test;

use Concat\Cache\CacheInterface;
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CacheHandlerTest extends TestCase
{
    public function tearDown(): void
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
        $cache = m::mock(CacheInterface::class);
        $handler = new CacheHandler($cache);
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
        $cache = m::mock(CacheInterface::class);
        $handler = new CacheHandler($cache);
        $handler->setOptions(['methods' => $methods]);

        $request = m::mock(Request::class);
        $request->shouldReceive('getMethod')->andReturn($method);

        $this->assertEquals($expected, $function->invoke($handler, $request));
    }

    private function mockResponse()
    {
        $stream = m::mock(StreamInterface::class);
        $stream->shouldReceive('__toString()')->andReturn('');

        $response = m::mock(Response::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);
        $response->shouldReceive('withBody')->andReturn($response);

        return $response;
    }

    public function testRequestShouldStore()
    {
        $response = $this->mockResponse();
        $cache = new ArrayCache();
        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 10,
        ]);

        $client = new Client(['handler' => $handler]);

        $client->get('/');
        $this->assertFalse($handler->lastRequestWasFetchedFromCache());

        $client->get('/');
        $this->assertTrue($handler->lastRequestWasFetchedFromCache());
    }

    public function testCacheShouldDeleteIfExpired()
    {
        $response = $this->mockResponse();

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

        $this->assertFalse($handler->lastRequestWasFetchedFromCache());

        // First request should cache the response
        $client->get('/');
        $this->assertFalse($handler->lastRequestWasFetchedFromCache());

        // This request should not fetch from cache because it's expired
        $client->get('/');
        $this->assertFalse($handler->lastRequestWasFetchedFromCache());
    }

    public function testCacheFetchException()
    {
        $this->expectException(RuntimeException::class);

        $response = $this->mockResponse();

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

    public function testCacheStoreException()
    {
        $this->expectException(RuntimeException::class);

        $response = $this->mockResponse();

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
        $response = $this->mockResponse();

        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 0,
        ]);

        $client = new Client(['handler' => $handler]);

        $client->get('/');
        $client->get('/');

        $this->assertFalse($handler->lastRequestWasFetchedFromCache());
    }

    public function testShouldNotCacheRequest()
    {
        $response = $this->mockResponse();
        $cache = new ArrayCache();

        $mockHandler = new MockHandler([
            $response,
        ]);

        $handler = new CacheHandler($cache, $mockHandler, [
            'expire' => 0,
            'methods' => ["PUT"],
        ]);

        $client = new Client(['handler' => $handler]);

        $client->get('/');
        $this->assertFalse($handler->lastRequestWasFetchedFromCache());
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

        $client->get('/');
        $this->assertFalse($handler->lastRequestWasFetchedFromCache());
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
        $response = $this->mockResponse();

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

        $this->addToAssertionCount(
            m::getContainer()->mockery_getExpectationCount()
        );
    }
}
