<?php

namespace Tests;

use CatLab\Eukles\Client\EuklesClient;
use PHPUnit\Framework\TestCase;

class SignatureTest extends TestCase
{
    private $secret = 'bcdefhijklmn';

    public function testSignParametersRoundTrip()
    {
        $client = new EuklesClient();

        $parameters = [
            'foo' => 'wololo',
            'bar' => 'awlololo'
        ];

        $signature = $client->signParameters($parameters, $this->secret);
        $this->assertTrue($client->isValidParameters($parameters, $signature, $this->secret));

        // Change one parameter -> invalid
        $parameters['foo'] = 'wololo2';
        $this->assertFalse($client->isValidParameters($parameters, $signature, $this->secret));
    }

    /**
     * The exact signature format 1.x produced and the Eukles server verifies:
     * sha256:<salt>:hash('sha256', http_build_query(ksort(params + salt + secret)))
     *
     * 1.x built this over $request->query() (Illuminate ParameterBag::all()) - functionally
     * identical to a plain associative array for this purpose.
     */
    public function testWireFormatCompatibility()
    {
        $client = new EuklesClient();

        $parameters = [ 'nonce' => '2026-07-09 12:00:00.000000' ];
        $salt = 'abcdefgh12345678';

        $base = http_build_query([
            'nonce' => '2026-07-09 12:00:00.000000',
            'salt' => $salt,
            'secret' => $this->secret
        ]);
        $expected = 'sha256:' . $salt . ':' . hash('sha256', $base);

        $this->assertTrue($client->isValidParameters($parameters, $expected, $this->secret));
    }

    /**
     * 1.x supported sha256/sha384/sha512 only - same allow-list in 2.0.
     */
    public function testAlternateAlgorithms()
    {
        $client = new EuklesClient();
        $parameters = [ 'nonce' => 'abc' ];

        foreach ([ 'sha384', 'sha512' ] as $algorithm) {
            $salt = 'salt-' . $algorithm;
            $base = http_build_query([
                'nonce' => 'abc',
                'salt' => $salt,
                'secret' => $this->secret
            ]);
            $expected = $algorithm . ':' . $salt . ':' . hash($algorithm, $base);

            $this->assertTrue($client->isValidParameters($parameters, $expected, $this->secret));
        }
    }

    public function testInvalidSignatureFormats()
    {
        $client = new EuklesClient();
        $this->assertFalse($client->isValidParameters([ 'a' => 'b' ], 'garbage', $this->secret));
        $this->assertFalse($client->isValidParameters([ 'a' => 'b' ], 'md5:salt:hash', $this->secret));
        $this->assertFalse($client->isValidParameters([ 'a' => 'b' ], '', $this->secret));
    }
}
