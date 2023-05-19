<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

class Memcache extends AbstractPhpExtension
{
    /** @var string */
    protected const EXTENSION_NAME = PhpExtension::MEMCACHE_EXTENSION;
}
