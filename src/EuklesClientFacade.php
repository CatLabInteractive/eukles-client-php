<?php

namespace CatLab\Eukles\Client;

use Illuminate\Support\Facades\Facade;

/**
 * Class EuklesClientFacade
 * @package CatLab\CentralStorage\Client
 */
class EuklesClientFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \CatLab\Eukles\Client\Interfaces\EuklesClient::class;
    }
}