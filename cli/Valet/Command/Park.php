<?php

declare(strict_types=1);

namespace Valet\Command;

use Valet\Configuration;

final class Park
{
    /**
     * @var \Valet\Configuration $configuration
     */
    private $configuration;

    /**
     * Park constructor.
     *
     * @param \Valet\Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function __invoke($path = null)
    {
        $this->configuration->addPath($path ?: getcwd());

        info(
            sprintf(
                "%s directory has been added to Valet's paths.",
                $path === null ? "This" : sprintf("The [%s]", $path)
            )
        );
    }
}
