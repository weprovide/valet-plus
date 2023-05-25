<?php

/**
 * We use Illuminate's Container to create a singleton class for extended valet classes.
 *
 */

use Illuminate\Container\Container;

Container::getInstance()->singleton(
    \Valet\Valet::class,
    \WeProvide\ValetPlus\Extended\Valet::class
);
Container::getInstance()->singleton(
    \Valet\PhpFpm::class,
    \WeProvide\ValetPlus\Extended\PhpFpm::class
);
Container::getInstance()->singleton(
    \Valet\Site::class,
    \WeProvide\ValetPlus\Extended\Site::class
);
Container::getInstance()->singleton(
    \Valet\Status::class,
    \WeProvide\ValetPlus\Extended\Status::class
);
