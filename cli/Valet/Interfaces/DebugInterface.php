<?php

namespace Valet\Interfaces;

use Valet\DebugMessage;

Interface DebugInterface
{

    /**
     * Debug the service.
     *
     * @return DebugMessage[]
     *   Array with DebugMessage objects.
     */
    public function debug();
}
