<?php

namespace CatLab\Eukles\Client\Models\Responses;

/**
 * Class TrackEventResponse
 * @package CatLab\Eukles\Client\Models\Responses
 */
class TrackEventResponse
{
    /**
     * @param array $data
     * @return TrackEventResponse
     */
    public static function fromData(array $data)
    {
        $out = new self();

        $out->setId($data['id']);
        $out->setType([$data['type']]);

        if (isset($data['triggeredEvents'])) {
            $out->setTriggeredEvents($data['triggeredEvents']);
        }

        return $out;
    }

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $triggeredEvents;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return TrackEventResponse
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return TrackEventResponse
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getTriggeredEvents()
    {
        return $this->triggeredEvents;
    }

    /**
     * @param int $triggeredEvents
     * @return TrackEventResponse
     */
    public function setTriggeredEvents($triggeredEvents)
    {
        $this->triggeredEvents = $triggeredEvents;
        return $this;
    }

    /**
     * Did this event trigger any listeners?
     * @return bool
     */
    public function didTriggerEvents()
    {
        return $this->getTriggeredEvents() > 0;
    }
}
