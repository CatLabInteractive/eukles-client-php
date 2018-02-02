<?php

namespace CatLab\Eukles\Client;

use Illuminate\Support\ServiceProvider;

/**
 * Class CentralStorageServiceProvider
 * @package CatLab\CentralStorage\Client
 */
class EuklesServiceProvider extends ServiceProvider
{
    /**
     *
     */
    public function register()
    {
        $this->app->bind(
            Interfaces\EuklesClient::class,
            function () {
                return EuklesClient::fromConfig();
            }
        );
    }
}