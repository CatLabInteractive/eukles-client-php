<?php

namespace CatLab\Eukles\Client\Exceptions;

use GuzzleHttp\Exception\RequestException;

/**
 * Class EuklesServerException
 * @package CatLab\Eukles\Client\Exceptions
 */
class EuklesServerException extends CentralStorageException
{
    /**
     * @param RequestException $e
     * @return StorageServerException
     */
    public static function make(RequestException $e)
    {
        $ex = new self('Eukles Server Exception: ' . $e->getMessage());
        if ($e->hasResponse()) {
            $ex->response = $e->getResponse()->getBody();
            $ex->responseHeaders = $e->getResponse()->getHeaders();
        }

        return $ex;
    }

    /**
     * @param $body
     * @return StorageServerException
     */
    public static function makeFromContent($body)
    {
        $ex = new self('Eukles returned invalid content (no json)');
        $ex->response = $body;

        return $ex;
    }

    /**
     * @var string
     */
    protected $response;

    /**
     * @var string[][]
     */
    protected $responseHeaders;

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return \string[][]
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }
}