<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use DomainException;
use Illuminate\Support\Collection;
use Valet\CommandLine;
use Valet\Filesystem;

use function Valet\info;
use function Valet\warning;

/**
 * Docker container service.
 * Please note that container names are restricted to the following characters [a-zA-Z0-9][a-zA-Z0-9_.-].
 * Obviously this has a dependency on the installation of Docker. For convenience install Docker Desktop.
 */
abstract class AbstractDockerService
{
    /** @var string */
    protected const DOCKER_COMPOSE_STUB = __DIR__ . '/../stubs/%s/%s/docker-compose.yml';
    /** @var string */
    protected const DOCKER_COMPOSE_PATH = VALET_HOME_PATH . '/Docker/%s/%s/docker-compose.yml';

    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;
    /** @var string */
    protected $serviceName;

    /**
     * @param CommandLine $cli
     * @param Filesystem $files
     */
    public function __construct(
        CommandLine $cli,
        Filesystem $files
    ) {
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Checks if Docker is available on the system.
     *
     * @return bool Returns true if Docker is available, false otherwise.
     */
    protected function isDockerAvailable(): bool
    {
        $command = 'command -v docker';
        $output = $this->cli->runAsUser($command);

        return !empty($output);
    }

    /**
     * Returns a collection of names of all running Docker containers.
     *
     * @return Collection
     */
    public function getAllRunningContainers(): Collection
    {
        if (!$this->isDockerAvailable()) {
            return collect([]);
        }

        $command = 'docker ps --format=\'{{.Names}}\'';
        $onError = function ($exitCode, $errorOutput) {
            warning($errorOutput);
        };

        return collect(array_filter(explode(PHP_EOL, $this->cli->runAsUser($command, $onError))));
    }

    /**
     * Runs a Docker command from the path where the docker-compose.yml is located.
     *
     * @param $command
     * @param $dir
     * @return $this
     */
    public function runCommand($command, $dir): self
    {
        if (!$this->isDockerAvailable()) {
            return $this;
        }

        $onError = function ($exitCode, $errorOutput) {
            warning($errorOutput);
        };

        $cwd = getcwd();
        @chdir($dir);
        $this->cli->runAsUser($command, $onError);
        @chdir($cwd);

        return $this;
    }

    /**
     * Starts the Docker container by the service's name. Creates the container first, if it doesn't exist yet.
     *
     * @param $name
     * @return $this
     */
    public function upContainer($name): self
    {
        if (!$this->isDockerAvailable()) {
            return $this;
        }

        info("Docker up version {$name} (this might take a while)...");
        $installPath = $this->getComposeInstallPath($name);
        $installDir  = $this->getComposeInstallDir($name);

        // Copy docker-compose.yml stub to installation/running path
        if (!$this->files->isDir($installDir)) {
            $this->files->mkdirAsUser($installDir);
        }
        $this->files->copyAsUser(
            $this->getComposeStubPath($name),
            $installPath
        );

        // Start the container the directory where the docker-compose.yml exists.
        $this->runCommand(
            'docker compose up --detach',
            $installDir
        );

        return $this;
    }

    /**
     * Stops the Docker container by the service's name.
     *
     * @param $name
     * @return $this
     */
    public function stopContainer($name): self
    {
        if (!$this->isDockerAvailable()) {
            return $this;
        }

        info("Docker stop version {$name}...");
        $this->runCommand(
            'docker compose stop',
            $this->getComposeInstallDir($name)
        );

        return $this;
    }

    /**
     * Stop and remove containers, networks, images and volumes by the service's name.
     *
     * @param $name
     * @return $this
     */
    public function downContainer($name): self
    {
        if (!$this->isDockerAvailable()) {
            return $this;
        }

        info("Docker down version {$name}...");
        $this->runCommand(
            'docker compose down --volumes --rmi all',
            $this->getComposeInstallDir($name)
        );

        return $this;
    }

    /**
     * Returns the short class name in lowercase.
     *
     * @return string
     */
    protected function getServiceName(): string
    {
        if (!$this->serviceName) {
            try {
                // We store the service's name in a property to prevent a lot of reflection (which is slow).
                $this->serviceName = strtolower((new \ReflectionClass($this))->getShortName());
            } catch (\ReflectionException $reflectionException) {
                echo 'Ohoh reflection exception';
                die();
            }
        }

        return $this->serviceName;
    }

    /**
     * Returns path of the docker-compose.yml stub file for the service.
     *
     * @param $name
     * @return string
     */
    protected function getComposeStubPath($name): string
    {
        return sprintf(
            static::DOCKER_COMPOSE_STUB,
            $this->getServiceName(),
            $name
        );
    }

    /**
     * Returns installation path of the docker-compose.yml stub file for the service.
     *
     * @param $name
     * @return string
     */
    protected function getComposeInstallPath($name): string
    {
        return sprintf(
            static::DOCKER_COMPOSE_PATH,
            $this->getServiceName(),
            $name
        );
    }

    /**
     * Returns the directory of the installation path of the docker-compose.yml stub file for the service.
     *
     * @param $name
     * @return string
     */
    protected function getComposeInstallDir($name): string
    {
        return dirname($this->getComposeInstallPath($name));
    }
}
