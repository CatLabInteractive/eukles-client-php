<?php

namespace Tests;

use CatLab\Eukles\Client\EuklesClient;
use CatLab\Eukles\Client\Exceptions\EuklesNamespaceException;
use CatLab\Eukles\Client\Exceptions\EuklesServerException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TrackEventRequestTest extends TestCase
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

    public function testTrackEventRequestShape()
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'id' => 5,
                'type' => 'quiz.played',
                'triggeredEvents' => 2
            ]))
        ]);

        $event = $client->createEvent('quiz.played', [ 'quiz' => new DummyModel() ]);
        $response = $client->trackEvent($event);

        $this->assertEquals(5, $response->getId());
        $this->assertEquals(2, $response->getTriggeredEvents());
        $this->assertTrue($response->didTriggerEvents());

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        // Method + url
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/v1/tracking/events.json', $request->getUri()->getPath());
        $this->assertEquals('eukles.example.com', $request->getUri()->getHost());
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals('consumer-key', $request->getHeaderLine('eukles-project-key'));

        // Signed query: only the nonce is signed, matching 1.x (which never included
        // the JSON body - or any other parameter - in the signature for trackEvent).
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertArrayHasKey('nonce', $query);
        $this->assertCount(1, $query);
        $this->assertTrue($client->isValidParameters(
            $query,
            $request->getHeaderLine('eukles-signature'),
            'consumer-secret'
        ));
        $this->assertFalse($client->isValidParameters(
            $query,
            $request->getHeaderLine('eukles-signature'),
            'wrong-secret'
        ));

        // JSON body carries type/data/actions plus the environment key
        $body = json_decode((string) $request->getBody(), true);
        $this->assertEquals('quiz.played', $body['type']);
        $this->assertEquals('testing', $body['environment']);
        $this->assertEquals([], $body['actions']['items']);

        $item = $body['data']['items'][0];
        $this->assertEquals('quiz', $item['role']);
        $this->assertEquals('dummy', $item['type']);
        $this->assertEquals(42, $item['uid']);
        $this->assertEquals([ 'foo' => 'bar' ], $item['attributes']);
    }

    public function testTrackEventRejectsEuklesNamespace()
    {
        $client = $this->makeClient([]);

        $this->expectException(EuklesNamespaceException::class);
        $client->trackEvent($client->createEvent('eukles.something'));
    }

    public function testTrackEventsIsANoOpLikeInOneX()
    {
        $client = $this->makeClient([]);

        $client->trackEvents([ $client->createEvent('quiz.played') ]);

        // No HTTP request should have been made.
        $this->assertCount(0, $this->history);
    }

    public function testTrackEventThrowsOnNonJsonResponse()
    {
        $client = $this->makeClient([ new Response(200, [], 'not json') ]);

        $this->expectException(EuklesServerException::class);
        $client->trackEvent($client->createEvent('quiz.played'));
    }

    public function testTrackEventThrowsServerExceptionOnHttpError()
    {
        $psr7Request = new Psr7Request('POST', 'https://eukles.example.com/api/v1/tracking/events.json');
        $mock = new MockHandler([
            new RequestException('Server error', $psr7Request, new Response(500, [], 'boom'))
        ]);
        $stack = HandlerStack::create($mock);

        $client = new EuklesClient(
            'https://eukles.example.com',
            'consumer-key',
            'consumer-secret',
            'testing',
            new Client([ 'handler' => $stack ])
        );

        $this->expectException(EuklesServerException::class);

        try {
            $client->trackEvent($client->createEvent('quiz.played'));
        } catch (EuklesServerException $e) {
            $this->assertStringContainsString('Server error', $e->getMessage());
            throw $e;
        }
    }
}
