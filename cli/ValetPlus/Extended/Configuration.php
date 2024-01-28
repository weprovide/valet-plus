<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Configuration as ValetConfiguration;

class Configuration extends ValetConfiguration
{
    /** @var string */
    protected const CUSTOM_DRIVERS_STUB_DIR = __DIR__ . '/../../stubs/drivers/custom/';
    /** @var string */
    protected const LOG_ROTATE_STUB_FILE = __DIR__ . '/../../stubs/newsyslog.d/valet-plus.conf';
    /** @var string */
    protected const LOG_ROTATE_FILE = '/etc/newsyslog.d/valet-plus.conf';

    /**
     * @inheritdoc
     */
    public function createDriversDirectory(): void
    {
        parent::createDriversDirectory();
        $this->installCustomDrivers();
    }

    /**
     * Installs custom drivers not supported by Valet.
     *
     * @return void
     */
    protected function installCustomDrivers(): void
    {
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

    /**
     * @inheritdoc
     */
    public function createLogDirectory(): void
    {
        parent::createLogDirectory();
        $this->installNewsysLogRotation();
    }

    /**
     * Installing configuration files for log file rotation.
     *
     * This log file rotation is using the default MacOS newsyslog. It places a config file in the newsyslog.d directory
     * which is automatically picked up and processed. You can run the following command to see what newsyslog will
     * rotate.
     *
     * Command: `sudo newsyslog -vn`
     *
     * See https://www.real-world-systems.com/docs/newsyslog.1.html
     *
     * @return void
     */
    protected function installNewsysLogRotation(): void
    {
        // Place config file to rotate log files in Valet+ log path.
        $contents = $this->files->get(static::LOG_ROTATE_STUB_FILE);
        $contents = str_replace('VALET_HOME_PATH', VALET_HOME_PATH, $contents);

        $this->files->putAsUser(
            static::LOG_ROTATE_FILE,
            $contents
        );

        //@todo; Set up log rotation for BREW_PREFIX . '/var/log/' to rotate elasticsearch logs
    }
}
