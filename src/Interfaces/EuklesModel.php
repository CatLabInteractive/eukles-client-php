<?php

namespace CatLab\Eukles\Client\Interfaces;

/**
 * Class EuklesModel
 * @package CatLab\Eukles\Client\Interfaces
 */
interface EuklesModel
{
    /**
     * @return array[]
     */
    public function getEuklesId();

    /**
     * @return array[]
     */
    public function getEuklesAttributes();

    /**
     * @return string
     */
    public function getEuklesType();
}