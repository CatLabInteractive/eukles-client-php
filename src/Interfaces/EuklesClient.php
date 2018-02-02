<?php

namespace CatLab\Eukles\Client\Interfaces;

use Illuminate\Http\Request;

/**
 * Interface CentralStorageClient
 * @package CatLab\CentralStorage\Client\Interfaces
 */
interface EuklesClient
{
    /**
     * Sign a request.
     * @param Request $response
     * @param $key
     * @param $secret
     * @return void
     */
    public function sign(Request $response, $key, $secret);

    /**
     * Check if a request is valid.
     * @param Request $request
     * @param $key
     * @param $secret
     * @return bool
     */
    public function isValid(Request $request, $key, $secret);
}