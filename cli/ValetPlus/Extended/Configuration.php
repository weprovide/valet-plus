<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Configuration as ValetConfiguration;

class Configuration extends ValetConfiguration
{
    /** @var string */
    protected const CUSTOM_DRIVERS_STUB_DIR = __DIR__ . '/../../stubs/drivers/custom/';

    /**
     * @inheritdoc
     */
    public function createDriversDirectory(): void
    {
        parent::createDriversDirectory();

        $driversDirectory = VALET_HOME_PATH . '/Drivers/';
        foreach (scandir(static::CUSTOM_DRIVERS_STUB_DIR) as $driver) {
            if (!is_dir($driver)) {
                $this->files->copy(
                    static::CUSTOM_DRIVERS_STUB_DIR . $driver,
                    $driversDirectory . $driver
                );
            }
        }
    }
}
