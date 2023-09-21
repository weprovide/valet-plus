<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use JsonException;
use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;

abstract class AbstractService
{
    /** @var bool */
    protected const STATE_DISABLED = false;
    /** @var bool */
    protected const STATE_ENABLED = true;

    /** @var Configuration */
    protected $configuration;
    /** @var string */
    protected $configClassName;
    /** @var Brew */
    protected $brew;
    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;

    /**
     * @param Configuration $configuration
     * @param Brew $brew
     * @param Filesystem $files
     * @param CommandLine $cli
     */
    public function __construct(
        Configuration $configuration,
        Brew          $brew,
        Filesystem    $files,
        CommandLine   $cli
    ) {
        $this->configuration = $configuration;
        $this->brew          = $brew;
        $this->files         = $files;
        $this->cli           = $cli;
    }

    /**
     * Returns the short class name in lowercase.
     *
     * @return string
     */
    public function getConfigClassName(): string
    {
        if (!$this->configClassName) {
            try {
                $this->configClassName = strtolower((new \ReflectionClass($this))->getShortName());
            } catch (\ReflectionException $reflectionException) {
                echo 'Ohoh reflection exception';
                die();
            }
        }

        return $this->configClassName;
    }

    /**
     * Returns whether the service is enabled or not.
     *
     * @return bool
     * @throws JsonException
     */
    public function isEnabled(): bool
    {
        $config = $this->configuration->read();
        $name   = $this->getConfigClassName();

        return (
            isset($config[$name]) &&
            isset($config[$name]['enabled']) &&
            $config[$name]['enabled'] == self::STATE_ENABLED
        );
    }

    /**
     * Stores the enabled state of the service in the configuration.
     *
     * @param bool $state
     * @throws JsonException
     */
    public function setEnabled(bool $state): void
    {
        $config = $this->configuration->read();
        $name   = $this->getConfigClassName();
        if (!isset($config[$name])) {
            $config[$name] = [];
        }
        $config[$name]['enabled'] = $state;
        $this->configuration->write($config);
    }

    /**
     * Removes the enabled state of the service from the configuration.
     *
     * @return void
     * @throws JsonException
     */
    public function removeEnabled(): void
    {
        $config = $this->configuration->read();
        $name   = $this->getConfigClassName();
        if (!isset($config[$name])) {
            $config[$name] = [];
        }
        if (isset($config[$name]['enabled'])) {
            unset($config[$name]['enabled']);
        }
        $config = array_filter($config);
        $this->configuration->write($config);
    }

    /**
     * Stops the service and stores in configuration it should not be started.
     *
     * @throws JsonException
     */
    public function disable(): void
    {
        $this->stop();
        $this->setEnabled(static::STATE_DISABLED);
    }

    /**
     * Installs the service if not installed, restarts it and stores in configuration it should be started.
     *
     * @throws JsonException
     */
    public function enable(): void
    {
        $this->setEnabled(static::STATE_ENABLED);
        if ($this->installed()) {
            $this->restart();

            return;
        }
        $this->install();
    }

    /**
     * Install the service.
     */
    abstract public function install(): void;

    /**
     * Returns whether the service is installed.
     *
     * @return bool|int
     */
    abstract public function installed(): mixed;

    /**
     * Stop the service.
     */
    abstract public function stop(): void;

    /**
     * Restart the service.
     */
    abstract public function restart(): void;

    /**
     * Uninstall the service.
     */
    abstract public function uninstall(): void;
}
