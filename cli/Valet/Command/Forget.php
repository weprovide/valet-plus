<?php

declare(strict_types=1);

namespace Valet\Command;

use Valet\Configuration;

final class Forget
{
    /**
     * @var \Valet\Configuration $configuration
     */
    private $configuration;

    /**
     * Forget constructor.
     *
     * @param \Valet\Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function __invoke($path = null)
    {
        $this->configuration->removePath($path ?: getcwd());

        info(
            sprintf(
                "%s directory has been removed from Valet's paths.",
                $path === null ? "This" : sprintf("The [%s]", $path)
            )
        );
    }

}
