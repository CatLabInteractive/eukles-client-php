<?php

namespace CatLab\Eukles\Client\Collections;

use CatLab\Base\Collections\Collection;
use CatLab\Eukles\Client\Models\OptIn;

/**
 * Class OptInCollection
 * @package CatLab\Eukles\Client\Collections
 */
class OptInCollection extends Collection
{
    /**
     * Create collection from api data.
     * @param array $data
     * @return OptInCollection
     */
    public static function fromData(array $data)
    {
        $collection = new OptInCollection();
        foreach ($data['items'] as $v) {
            $collection->add(OptIn::fromData($v));
        }

        return $collection;
    }

    /**
     * @param $id
     * @return OptIn
     */
    public function getFromId($id)
    {
        foreach ($this as $v) {
            /** @var OptIn $v */
            if ($v->getId() == $id) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Check if all required optins have been set.
     * @return bool
     */
    public function isValid()
    {
        foreach ($this as $v) {
            /** @var OptIn $v */
            if ($v->isRequired() && !$v->isAccepted()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Translate to data that can be consumed by the API.
     */
    public function toReplyData()
    {
        $out = [
            'items' => []
        ];

        foreach ($this as $v) {
            /** @var OptIn $v */
            $out['items'][] = [
                'id' => $v->getId(),
                'reply' => [
                    'accepted' => $v->isAccepted()
                ]
            ];
        }

        return $out;
    }
}