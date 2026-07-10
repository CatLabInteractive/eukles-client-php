<?php

namespace CatLab\Eukles\Client\Interfaces;

/**
 * Interface EuklesClient
 * @package CatLab\Eukles\Client\Interfaces
 */
interface EuklesClient
{
    /**
     * Sign a set of parameters.
     * @param array $parameters
     * @param string|null $secret
     * @return string
     */
    public function signParameters(array $parameters, $secret = null);

    /**
     * Check if a signature is valid for the given parameters.
     * @param array $parameters
     * @param string $providedSignature
     * @param string $secret
     * @return bool
     */
    public function isValidParameters(array $parameters, $providedSignature, $secret);
}
