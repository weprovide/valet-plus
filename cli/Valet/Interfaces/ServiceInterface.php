<?php

namespace Valet\Interfaces;

Interface ServiceInterface
{

    /**
     * Install the service.
     *
     * @return mixed
     */
    public function install();

    /**
     * Is the service installed.
     *
     * @return mixed
     */
    public function installed();

    /**
     * Stop the service.
     *
     * @return mixed
     */
    public function stop();

    /**
     * Restart the service.
     *
     * @return mixed
     */
    public function restart();
}
