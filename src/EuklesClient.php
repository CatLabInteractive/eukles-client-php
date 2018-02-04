<?php

namespace CatLab\Eukles\Client;

use CatLab\CentralStorage\Client\Exceptions\StorageServerException;
use CatLab\CentralStorage\Client\Interfaces\CentralStorageClient as CentralStorageClientInterface;
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Eukles\Client\Exceptions\EuklesServerException;
use CatLab\Eukles\Client\Exceptions\InvalidModel;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class EuklesClient
 * @package CatLab\Eukles\Client
 */
class EuklesClient
{
    const QUERY_NONCE       = 'nonce';

    const HEADER_SIGNATURE  = 'eukles-signature';
    const HEADER_KEY        = 'eukles-project-key';
    const ENVIRONMENT_KEY   = 'eukles-environment';

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
     * @return self
     */
    public static function fromConfig()
    {
        $client = new self(
            \Config::get('eukles.server'),
            \Config::get('eukles.key'),
            \Config::get('eukles.secret'),
            \Config::get('eukles.environment')
        );

        return $client;
    }

    /**
     * CentralStorageClient constructor.
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
        ClientInterface $httpClient = null
    ) {
        if (!isset($httpClient)) {
            $httpClient = new GuzzleClient();
        }
        $this->httpClient = $httpClient;

        $this->server = $server;
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->environment = $environment;
    }

    /**
     * Sign a request.
     * @param Request $request
     * @param $key
     * @param $secret
     * @return void
     */
    public function sign(Request $request, $key = null, $secret = null)
    {
        $key = $key ?? $this->consumerKey;
        $secret = $secret ?? $this->consumerSecret;

        // Add a nonce that we won't check but we add it anyway.
        $request->query->set(self::QUERY_NONCE, $this->getNonce());

        $signature = $this->getSignature($request, $this->algorithm, $secret);

        $request->headers->set(self::HEADER_SIGNATURE, $signature);
        $request->headers->set(self::HEADER_KEY, $key);
    }

    /**
     * Check if a request is valid.
     * @param Request $request
     * @param $key
     * @param $secret
     * @return bool
     */
    public function isValid(Request $request, $key, $secret)
    {
        $fullSignature = $request->headers->get(self::HEADER_SIGNATURE);
        if (!$fullSignature) {
            return false;
        }

        $signatureParts = explode(':', $fullSignature);
        if (count($signatureParts) != 3) {
            return false;
        }

        $algorithm = array_shift($signatureParts);
        $salt = array_shift($signatureParts);
        $signature = array_shift($signatureParts);

        $actualSignature = $this->getSignature($request, $algorithm, $secret, $salt);
        if (!$actualSignature) {
            return false;
        }

        return $fullSignature === $actualSignature;
    }

    /**
     * @param $eventType
     * @param $objects
     * @throws EuklesServerException
     */
    public function trackEvent($eventType, $objects)
    {
        $translatedObjects = [];
        foreach ($objects as $role => $object) {
            // check what kind of array this is
            if (is_array($object) && isset($object['type'])) {
                $translatedObjects[] = $this->translateObject($role, $object);
            } else {
                // need to go deeper.
                foreach ($object as $v) {
                    $translatedObjects[] = $this->translateObject($role, $v);
                }
            }
        }

        $data = [
            'type' => $eventType,
            'data' => [
                'items' => $translatedObjects
            ]
        ];

        $url = $this->getUrl('events.json');
        $request = Request::create($url, 'POST');
        $request->headers->replace([
            'Content-Type' => 'application/json'
        ]);

        $request->input = new ParameterBag($data);

        $this->sign($request);

        try {
            $result = $this->send($request);
        } catch (RequestException $e) {
            throw EuklesServerException::make($e);
        }
    }

    /**
     * @param $role
     * @param $object
     * @return array
     * @throws InvalidModel
     */
    protected function translateObject($role, $object)
    {
        if ($object instanceof EuklesModel) {
            return [
                'uid' => $object->getEuklesId(),
                'role' => $role,
                'type' => $object->getEuklesType(),
                'attributes' => $object->getEuklesAttributes()
            ];
        } elseif (
            is_object($object) ||
            is_array($object)
        ) {
            $parameters = is_object($object) ? get_object_vars($object) : $object;
            if (!isset($parameters['type'])) {
                throw InvalidModel::make($parameters);
            }

            $params = [
                'role' => $role
            ];

            $params['attributes'] = $parameters;
            $params['type'] = $parameters['type'];

            unset($params['attributes']['type']);

            if (isset($parameters['uid'])) {
                $params['uid'] = $parameters['uid'];
                unset($params['attributes']['uid']);
            }

            return $params;
        }

        throw InvalidModel::make($object);
    }

    /**
     * @param Request $request
     * @param $algorithm
     * @param $secret
     * @param null $salt
     * @return string
     */
    protected function getSignature(Request $request, $algorithm, $secret, $salt = null)
    {
        if (!$this->isValidAlgorithm($algorithm)) {
            return false;
        }

        $inputs = $request->query();

        // Add some salt
        if (!isset($salt)) {
            $salt = str_random(16);
        }

        $inputs['salt'] = $salt;
        $inputs['secret'] = $secret;

        // Sort on key
        ksort($inputs);

        // Turn into a string
        $base = http_build_query($inputs);

        // And... hash!
        $signature = hash($algorithm, $base);

        return $algorithm . ':' . $inputs['salt'] . ':' . $signature;
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
     * @return false|string
     */
    protected function getNonce()
    {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, $t) );

        return $d->format("Y-m-d H:i:s.u");
    }

    /**
     * @param $path
     * @param null $server
     * @return string
     */
    protected function getUrl($path, $server = null)
    {
        $server = $server ?? $this->server;
        return $server . '/api/v1/' . $path;
    }

    /**
     * @param Request $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function send(Request $request)
    {
        $method = $request->getMethod();
        $url = $request->getUri();

        $options = [
            'headers' => $request->headers->all(),
            'query' => $request->query->all()
        ];

        if ($request->files->count() > 0) {

            $elements = [];
            foreach ($request->input() as $k => $v) {
                if (is_scalar($v)) {
                    $elements[] = [
                        'name' => $k,
                        'contents' => $v
                    ];
                }
            }

            $counter = 0;
            foreach ($request->files as $file) {

                /** @var UploadedFile $file */
                $filename = addslashes($file->getClientOriginalName());

                if (empty($filename)) {
                    $filename = $file->getFilename();
                }

                $elements[] = [
                    'name' => 'file_' . (++ $counter),
                    'contents' => fopen($file->path(), 'r'),
                    'filename' => $file->path(),
                    'headers' => [
                        'Content-Disposition' => 'form-data; name="file_' . (++ $counter) . '"; filename="' . $filename . '"'
                    ]
                ];
            }

            $options['multipart'] = $elements;
        } elseif ($request->input) {
            $options['json'] = $request->input->all();
        }

        //dd($psr7Request)
        $response = $this->httpClient->request($method, $url, $options);

        return $response;
    }
}