<?php

/**
 * Basic drivers...
 */
require_once __DIR__.'/ValetDriver.php';
require_once __DIR__.'/BasicValetDriver.php';

$drivers = scandir(__DIR__);
$drivers = array_diff($drivers, ['ValetDriver.php', 'BasicValetDriver.php']);

foreach ($drivers as $driver) {
    if (strpos($driver, 'ValetDriver') !== false) {
        require_once(__DIR__.'/'.$driver);
    }
}
