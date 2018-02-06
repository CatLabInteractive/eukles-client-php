<?php

namespace CatLab\Eukles\Client\Models;
use CatLab\Eukles\Client\Exceptions\InvalidModel;
use CatLab\Eukles\Client\Interfaces\EuklesModel;

/**
 * Class Event
 * @package CatLab\Eukles\Client\Models
 */
class Event
{
    /**
     * @param $type
     * @param $objects
     * @return Event
     */
    public static function create($type, $objects = null)
    {
        $instance = new self($type);

        if ($objects === null) {
            $instance->objects = $objects;
        }

        return $instance;
    }

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $objects = [];

    /**
     * @var array
     */
    protected $actions = [];

    /**
     * Event constructor.
     * @param $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @param string $role
     * @param mixed $object
     * @return $this
     */
    public function setObject($role, $object)
    {
        if (!isset($this->objects[$role])) {
            $this->objects[$role] = [];
        }

        $this->objects[$role][] = $object;
        return $this;
    }

    /**
     * @param $a
     * @param $relationship
     * @param $b
     * @return $this
     * @throws InvalidModel
     */
    public function link($a, $relationship, $b)
    {
        $this->actions[] = $this->getLinkAction('link', $relationship, $a, $b);
        return $this;
    }

    /**
     * @param $a
     * @param $relationship
     * @param $b
     * @return $this
     * @throws InvalidModel
     */
    public function unlink($a, $relationship, $b)
    {
        $this->actions[] = $this->getLinkAction('unlink', $relationship, $a, $b);
        return $this;
    }

    /**
     * @throws InvalidModel
     */
    public function getData()
    {
        $translatedObjects = [];
        foreach ($this->objects as $role => $object) {
            // check what kind of array this is
            if (is_array($object) && isset($object['type'])) {
                $translatedObjects[] = $this->translateObject($role, $object);
            } else {
                // need to go deeper.
                foreach ($object as $v) {
                    $translatedObjects[] = $this->translateObject($role, $v);
                }
            }
        }

        return [
            'type' => $this->type,
            'data' => [
                'items' => $translatedObjects
            ],
            'actions' => [
                'items' => $this->actions
            ]
        ];
    }

    /**
     * @param $role
     * @param $object
     * @return array
     * @throws InvalidModel
     */
    protected function translateObject($role, $object)
    {
        if ($object instanceof EuklesModel) {
            return [
                'uid' => $object->getEuklesId(),
                'role' => $role,
                'type' => $object->getEuklesType(),
                'attributes' => $object->getEuklesAttributes()
            ];
        } elseif (
            is_object($object) ||
            is_array($object)
        ) {
            $parameters = is_object($object) ? get_object_vars($object) : $object;
            if (!isset($parameters['type'])) {
                throw InvalidModel::make($parameters);
            }

            $params = [
                'role' => $role
            ];

            $params['attributes'] = $parameters;
            $params['type'] = $parameters['type'];

            unset($params['attributes']['type']);

            if (isset($parameters['uid'])) {
                $params['uid'] = $parameters['uid'];
                unset($params['attributes']['uid']);
            }

            return $params;
        }

        throw InvalidModel::make($object);
    }

    /**
     * @param $object
     * @return array
     * @throws InvalidModel
     */
    protected function translateObjectToIdentifiers($object)
    {
        if ($object instanceof EuklesModel) {
            return [
                'uid' => $object->getEuklesId(),
                'type' => $object->getEuklesType()
            ];
        } elseif (
            is_object($object) ||
            is_array($object)
        ) {
            $parameters = is_object($object) ? get_object_vars($object) : $object;
            if (
                !isset($parameters['type']) ||
                !isset($parameters['uid'])
            ) {
                throw InvalidModel::make($parameters);
            }

            return [
                'uid' => $parameters['uid'],
                'type' => $parameters['type']
            ];
        }

        throw InvalidModel::make($object);
    }

    /**
     * @param $action
     * @param $relationship
     * @param $a
     * @param $b
     * @return array
     * @throws InvalidModel
     */
    protected function getLinkAction($action, $relationship, $a, $b)
    {
        return [
            'action' => $action,
            'relationship' => $relationship,
            'models' => [
                'items' => [
                    $this->translateObjectToIdentifiers($a),
                    $this->translateObjectToIdentifiers($b)
                ]
            ]
        ];
    }
}