<?php

namespace CatLab\Eukles\Client\Models;

use DateTime;

/**
 * Class OptIn
 * @package CatLab\Eukles\Client\Models
 */
class OptIn
{
    /**
     * @param $data
     * @return OptIn
     */
    public static function fromData($data)
    {
        $optin =  new self(
            $data['id'],
            $data['short'],
            $data['summary'],
            $data['required']
        );

        if (isset($data['reply']) && isset($data['reply']['accepted'])) {
            $optin->setAccepted($data['reply']['accepted']);
        }

        if (isset($data['reply']) && isset($data['reply']['date'])) {
            $optin->setDate(new DateTime($data['reply']['date']));
        }

        return $optin;
    }

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $short;

    /**
     * @var string
     */
    protected $summary;

    /**
     * @var bool
     */
    protected $required;

    /**
     * @var bool
     */
    protected $accepted = false;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * OptIn constructor.
     * @param $id
     * @param $short
     * @param $summary
     * @param $required
     */
    public function __construct($id, $short, $summary, $required)
    {
        $this->id = $id;
        $this->short = $short;
        $this->summary = $summary;
        $this->required = $required;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getShort()
    {
        return $this->short;
    }

    /**
     * @return string
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @return bool
     */
    public function isAccepted()
    {
        return $this->accepted;
    }

    /**
     * @param bool $accepted
     * @return OptIn
     */
    public function setAccepted(bool $accepted)
    {
        $this->accepted = $accepted;
        return $this;
    }

    /**
     * @param DateTime $date
     * @return $this
     */
    public function setDate(DateTime $date)
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }
}
