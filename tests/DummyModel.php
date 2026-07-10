<?php

namespace Tests;

use CatLab\Eukles\Client\Interfaces\EuklesModel;

/**
 * Minimal EuklesModel implementation used by the request-shape tests, mirroring how
 * QuizWitz models implement the interface (see e.g. QuizWitz\Models\Share).
 */
class DummyModel implements EuklesModel
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $attributes;

    public function __construct($id = 42, $type = 'dummy', array $attributes = [ 'foo' => 'bar' ])
    {
        $this->id = $id;
        $this->type = $type;
        $this->attributes = $attributes;
    }

    public function getEuklesId()
    {
        return $this->id;
    }

    public function getEuklesAttributes()
    {
        return $this->attributes;
    }

    public function getEuklesType()
    {
        return $this->type;
    }
}
