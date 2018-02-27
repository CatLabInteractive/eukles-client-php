<?php

namespace CatLab\Eukles\Client\Exceptions;

/**
 * Class InvalidModel
 * @package CatLab\Eukles\Client\Exceptions
 */
class InvalidModel extends EuklesException
{
    /**
     * @param $object
     * @return InvalidModel
     */
    public static function make($object)
    {
        return new self("Invalid object type provided.");
    }
}