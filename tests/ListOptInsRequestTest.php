<?php

namespace Tests;

use CatLab\Eukles\Client\Collections\OptInCollection;
use CatLab\Eukles\Client\EuklesClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ListOptInsRequestTest extends TestCase
{
    public function testRequestsTheTypeRouteAndParsesTheItems()
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'items' => [
                    ['id' => 12, 'short' => 'Newsletter', 'summary' => 'Monthly', 'required' => false],
                    ['id' => 15, 'short' => 'Terms', 'summary' => '', 'required' => true],
                ],
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new EuklesClient(
            'https://eukles.test/',
            'key',
            'secret',
            'testing',
            new Client(['handler' => $stack])
        );

        $optIns = $client->listOptIns('user', 'nl');

        $this->assertInstanceOf(OptInCollection::class, $optIns);
        $this->assertCount(2, $optIns);
        $this->assertSame('Newsletter', $optIns->getFromId(12)->getShort());
        $this->assertTrue($optIns->getFromId(15)->isRequired());

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/tracking/models/user/optins.json', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('testing', $query['environment']);
        $this->assertSame('nl', $query['language']);
        $this->assertNotEmpty($request->getHeaderLine(EuklesClient::HEADER_SIGNATURE));
    }
}
