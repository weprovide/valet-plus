<?php

use Illuminate\Container\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

// Create instance of event dispatcher.
$container = Container::getInstance();
$container->instance('event_dispatcher', new EventDispatcher());

// Register classes to events.
Mailhog::register();
