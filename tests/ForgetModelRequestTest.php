<?php

namespace Tests;

use CatLab\Eukles\Client\EuklesClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ForgetModelRequestTest extends TestCase
{
    private $history = [];

    private function makeClient(array $responses): EuklesClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new EuklesClient(
            'https://eukles.example.com',
            'consumer-key',
            'consumer-secret',
            'testing',
            new Client([ 'handler' => $stack ])
        );
    }

    public function testForgetModelSendsAnEuklesForgetEvent()
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([ 'id' => 1, 'type' => 'eukles.forget' ]))
        ]);

        $client->forgetModel(new DummyModel(7, 'user', []));

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        // Same endpoint/method as any other tracked event - forgetModel is trackEvent underneath.
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/v1/tracking/events.json', $request->getUri()->getPath());
        $this->assertEquals('consumer-key', $request->getHeaderLine('eukles-project-key'));

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertArrayHasKey('nonce', $query);
        $this->assertTrue($client->isValidParameters(
            $query,
            $request->getHeaderLine('eukles-signature'),
            'consumer-secret'
        ));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertEquals('eukles.forget', $body['type']);
        $this->assertEquals('testing', $body['environment']);

        $item = $body['data']['items'][0];
        $this->assertEquals('source', $item['role']);
        $this->assertEquals('user', $item['type']);
        $this->assertEquals(7, $item['uid']);
    }

    /**
     * 1.x: forgetModel/syncRelationship temporarily disable the eukles.* namespace guard so the
     * internal "eukles.forget" event type itself is allowed through. This must not leak - a
     * regular trackEvent() call right after must still enforce the guard.
     */
    public function testNamespaceGuardIsRestoredAfterForgetModel()
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([ 'id' => 1, 'type' => 'eukles.forget' ]))
        ]);

        $client->forgetModel(new DummyModel(7, 'user', []));

        $this->expectException(\CatLab\Eukles\Client\Exceptions\EuklesNamespaceException::class);
        $client->trackEvent($client->createEvent('eukles.custom'));
    }
}
