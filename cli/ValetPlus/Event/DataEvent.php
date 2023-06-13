<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Event;

use Symfony\Contracts\EventDispatcher\Event;

class DataEvent extends Event
{
    /** @var array */
    protected $data = [];

    /**
     * Set a value.
     *
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Get a value.
     *
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return null;
    }
}
