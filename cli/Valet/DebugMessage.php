<?php

namespace Valet;

class DebugMessage
{

    public $message;
    public $type;
    public $typeLabels = [
        LOG_INFO => 'info',
        LOG_WARNING => 'warning',
    ];

    /**
     * DebugMessage constructor.
     *
     * @param $message
     *   The debug message.
     * @param $type
     *   Currently supports: LOG_INFO, LOG_WARNING.
     *   @link https://php.net/manual/en/network.constants.php
     */
    public function __construct($message, $type)
    {
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Sets the message.
     *
     * @param $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns the type of the message.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the type of warning, defaults to info.
     *
     * @return string
     */
    public function getTypeLabel()
    {
        $type = isset($this->typeLabels[$this->type]) ? $this->type : LOG_INFO;
        return $this->typeLabels[$type];
    }

    /**
     * Adds given prefix to the message.
     *
     * @param $prefix
     */
    public function prefixMessage($prefix)
    {
        $this->setMessage($prefix . $this->getMessage());
    }

    /**
     * Adds given suffix to the message.
     *
     * @param $suffix
     */
    public function suffixMessage($suffix)
    {
        $this->setMessage($this->getMessage() . $suffix);
    }
}
