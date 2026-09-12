<?php

namespace CatLab\Eukles\Client;

use CatLab\Eukles\Client\Collections\OptInCollection;
use CatLab\Eukles\Client\Exceptions\EuklesNamespaceException;
use CatLab\Eukles\Client\Exceptions\EuklesServerException;
use CatLab\Eukles\Client\Exceptions\InvalidModel;
use CatLab\Eukles\Client\Interfaces\EuklesClient as EuklesClientInterface;
use CatLab\Eukles\Client\Models\Event;
use CatLab\Eukles\Client\Models\Responses\TrackEventResponse;
use CatLab\Eukles\Client\Tools\StringHelper;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class EuklesClient
 * @package CatLab\Eukles\Client
 */
class EuklesClient implements EuklesClientInterface
{
    const QUERY_NONCE       = 'nonce';

    const HEADER_SIGNATURE  = 'eukles-signature';
    const HEADER_KEY        = 'eukles-project-key';
    const ENVIRONMENT_KEY   = 'eukles-environment';

    const EUKLES_NAMESPACE = 'eukles';

    /**
     * @var string
     */
    protected $algorithm = 'sha256';

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $server;

    /**
     * @var string
     */
    protected $consumerKey;

    /**
     * @var string
     */
    protected $consumerSecret;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var bool
     */
    private $protectEuklesNamespace = true;

    /**
     * EuklesClient constructor.
     * @param null $server
     * @param null $consumerKey
     * @param null $consumerSecret
     * @param null $environment
     * @param ClientInterface|null $httpClient
     */
    public function __construct(
        $server = null,
        $consumerKey = null,
        $consumerSecret = null,
        $environment = null,
        ?ClientInterface $httpClient = null
    ) {
        if (!isset($httpClient)) {
            $httpClient = new GuzzleClient();
        }
        $this->httpClient = $httpClient;

        // Make server safe
        if (mb_substr((string) $server, -1) === '/') {
            $server = mb_substr($server, 0, -1);
        }

        $this->server = $server;
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->environment = $environment;
    }

    /**
     * Sign a set of parameters.
     * @param array $parameters
     * @param string|null $secret
     * @return string
     */
    public function signParameters(array $parameters, $secret = null)
    {
        $secret = $secret ?? $this->consumerSecret;

        return $this->getSignature($parameters, $this->algorithm, $secret);
    }

    /**
     * Check if a signature is valid for the given parameters.
     * @param array $parameters
     * @param $providedSignature
     * @param $secret
     * @return bool
     */
    public function isValidParameters(array $parameters, $providedSignature, $secret)
    {
        if (!$providedSignature) {
            return false;
        }

        $signatureParts = explode(':', $providedSignature);
        if (count($signatureParts) != 3) {
            return false;
        }

        $algorithm = array_shift($signatureParts);
        $salt = array_shift($signatureParts);

        $actualSignature = $this->getSignature($parameters, $algorithm, $secret, $salt);
        if (!$actualSignature) {
            return false;
        }

        return $providedSignature === $actualSignature;
    }

    /**
     * @param Event $event
     * @return TrackEventResponse
     * @throws EuklesNamespaceException
     * @throws EuklesServerException
     * @throws InvalidModel
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function trackEvent(Event $event)
    {
        $this->checkValidNamespace($event);

        $body = $event->getData();
        $body['environment'] = $this->environment;

        $url = $this->getUrl('events.json');

        try {
            $result = $this->send('POST', $url, [], $body);

            $jsonContent = $result->getBody()->getContents();
            $data = json_decode($jsonContent, true);
            if (!$data) {
                throw new EuklesServerException('Could not decode Eukles response: ' . $jsonContent);
            }

            return TrackEventResponse::fromData($data);
        } catch (RequestException $e) {
            throw EuklesServerException::make($e);
        }
    }

    /**
     * Event[] $events
     * @param Event[] $events
     * @return void
     */
    public function trackEvents(array $events)
    {
        // 1.x compatibility: trackEvents() was never implemented upstream (no-op).
    }

    /**
     * @param $eventType
     * @param null $objects
     * @return Event
     */
    public function createEvent($eventType, $objects = null)
    {
        return Event::create($eventType, $objects);
    }

    /**
     * @param $modelType
     * @param $modelUid
     * @param string $language
     * @param null $context
     * @return OptInCollection
     * @throws EuklesServerException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOptIns($modelType, $modelUid, $language = 'en', $context = null)
    {
        $url = $this->getUrl('models/' . $modelType . '/' . $modelUid  . '/optins.json');

        $query = [
            'environment' => $this->environment,
            'language' => $language
        ];

        if ($context) {
            $query['context'] = $context;
        }

        try {
            $result = $this->send('GET', $url, $query);
        } catch (RequestException $e) {
            throw EuklesServerException::make($e);
        }

        $jsonContent = $result->getBody()->getContents();
        $data = json_decode($jsonContent, true);

        return OptInCollection::fromData($data);
    }

    /**
     * The opt-in campaigns of a model type, without a model: no reply data,
     * texts in $language. Use it to render consent checkboxes for someone
     * who is not tracked yet.
     * @throws EuklesServerException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function listOptIns(string $modelType, ?string $language = 'en', ?string $context = null): OptInCollection
    {
        $language = $language ?: 'en';

        $url = $this->getUrl('models/' . $modelType . '/optins.json');

        $query = [
            'environment' => $this->environment,
            'language' => $language
        ];
        if ($context) {
            $query['context'] = $context;
        }

        try {
            $result = $this->send('GET', $url, $query);
        } catch (RequestException $e) {
            throw EuklesServerException::make($e);
        }

        $data = json_decode($result->getBody()->getContents(), true);

        return OptInCollection::fromData($data);
    }

    /**
     * @param $modelType
     * @param $modelUid
     * @param OptInCollection $optIns
     * @param string $language
     * @return OptInCollection
     * @throws EuklesServerException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setOptIns($modelType, $modelUid, OptInCollection $optIns, $language = 'en')
    {
        $body = $optIns->toReplyData();

        $url = $this->getUrl('models/' . $modelType . '/' . $modelUid  . '/optins.json');

        $query = [
            'environment' => $this->environment,
            'language' => $language
        ];

        try {
            $result = $this->send('POST', $url, $query, $body);
        } catch (RequestException $e) {
            throw EuklesServerException::make($e);
        }

        $jsonContent = $result->getBody()->getContents();

        $data = json_decode($jsonContent, true);
        return OptInCollection::fromData($data);
    }

    /**
     * Synchronize the relationship of an object.
     * Note that any existing relationships from model with type $relatonship will be
     * removed if not available in the $targets array.
     * Note that sync events do not trigger actions.
     * @param $model
     * @param $relationship
     * @param $targets
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function syncRelationship($model, $relationship, $targets)
    {
        $previousProtectState = $this->protectEuklesNamespace;
        $this->protectEuklesNamespace = false;

        try {
            $event = self::createEvent('eukles.sync.' . $relationship);
            $event->setObject('source', $model);
            foreach ($targets as $target) {
                $event->setObject('link', $target);
            }
            $this->trackEvent($event);
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->protectEuklesNamespace = $previousProtectState;
        }
    }

    /**
     * Completely forget a model.
     * @param $model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function forgetModel($model)
    {
        $previousProtectState = $this->protectEuklesNamespace;
        $this->protectEuklesNamespace = false;

        try {
            $event = self::createEvent('eukles.forget');
            $event->setObject('source', $model);
            $this->trackEvent($event);
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->protectEuklesNamespace = $previousProtectState;
        }
    }

    /**
     * Sign and send a request to the Eukles server.
     *
     * Only the query parameters (plus a generated nonce) are signed - this mirrors 1.x, which
     * signed $request->query() only and never included the JSON body in the signature.
     * @param string $method
     * @param string $url
     * @param array $query
     * @param array|null $jsonBody
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function send(string $method, string $url, array $query = [], ?array $jsonBody = null)
    {
        $query[self::QUERY_NONCE] = $this->getNonce();

        $signature = $this->getSignature($query, $this->algorithm, $this->consumerSecret);

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                self::HEADER_SIGNATURE => $signature,
                self::HEADER_KEY => $this->consumerKey
            ],
            'query' => $query
        ];

        if ($jsonBody !== null) {
            $options['json'] = $jsonBody;
        }

        return $this->httpClient->request($method, $url, $options);
    }

    /**
     * Same as getSignature, but doesn't require a request.
     * @param array $parameters
     * @param $algorithm
     * @param $secret
     * @param null $salt
     * @return string|false
     */
    protected function getSignature(array $parameters, $algorithm, $secret, $salt = null)
    {
        if (!$this->isValidAlgorithm($algorithm)) {
            return false;
        }

        // Add some salt
        if (!isset($salt)) {
            $salt = StringHelper::random(16);
        }

        $parameters['salt'] = $salt;
        $parameters['secret'] = $secret;

        // Sort on key
        ksort($parameters);

        // Turn into a string
        $base = http_build_query($parameters);

        // And... hash!
        $signature = hash($algorithm, $base);

        return $algorithm . ':' . $parameters['salt'] . ':' . $signature;
    }

    /**
     * @param string $algorithm
     * @return bool
     */
    protected function isValidAlgorithm($algorithm)
    {
        switch ($algorithm) {
            case 'sha256':
            case 'sha384':
            case 'sha512':
                return true;

            default:
                return false;
        }
    }

    /**
     * @return string
     */
    protected function getNonce()
    {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, (int) floor($t)) );

        return $d->format("Y-m-d H:i:s.u");
    }

    /**
     * @param $path
     * @param null $server
     * @return string
     */
    protected function getUrl($path, $server = null)
    {
        if (isset($server)) {
            // Make server safe
            if (mb_substr($server, -1) === '/') {
                $server = mb_substr($server, 0, -1);
            }
        } else {
            $server = $this->server;
        }
        return $server . '/api/v1/tracking/' . $path;
    }

    /**
     * Check if the event namespace is valid.
     * @param $event
     * @throws EuklesNamespaceException
     */
    protected function checkValidNamespace(Event $event)
    {
        if (!$this->protectEuklesNamespace) {
            return;
        }

        $name = $event->getType();
        $ns = self::EUKLES_NAMESPACE . '.';

        if (mb_substr(mb_strtoupper($name), 0, mb_strlen($ns)) === mb_strtoupper($ns)) {
            throw new EuklesNamespaceException("Event namespaces should not start with " . self::EUKLES_NAMESPACE);
        }
    }
}
