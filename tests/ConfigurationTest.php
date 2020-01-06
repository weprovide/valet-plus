<?php

use Illuminate\Container\Container;
use Valet\Configuration;
use Valet\Filesystem;

class ConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $_SERVER['SUDO_USER'] = user();

        Container::setInstance(new Container());
    }

    public function tearDown(): void
    {
        $container = Mockery::getContainer();
        $this->addToAssertionCount($container->mockery_getExpectationCount());

        Mockery::close();
    }

    public function testConfigurationDirectoryIsCreatedIfItDoesntExist()
    {
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('ensureDirExists')->once()->with(
            VALET_HOME_PATH,
            user()
        );

        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createConfigurationDirectory();
    }

    public function testDriversDirectoryIsCreatedWithSampleDriverIfNonExistant()
    {
        $files = Mockery::mock(
            Filesystem::class . '[isDir,mkdirAsUser,putAsUser]'
        );

        $files->shouldReceive('isDir')
            ->with(VALET_HOME_PATH . '/Drivers')
            ->andReturn(false);

        $files->shouldReceive('mkdirAsUser')->with(
            VALET_HOME_PATH . '/Drivers'
        );

        $files->shouldReceive('putAsUser');
        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createDriversDirectory();
    }

    public function testLogDirectoryIsCreatedWithLogFilesIfItDoesntExist()
    {
        $files = Mockery::mock(Filesystem::class . '[ensureDirExists,touch]');

        $files->shouldReceive('ensureDirExists')->with(
            VALET_HOME_PATH . '/Log',
            user()
        );

        $files->shouldReceive('touch')->once();
        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createLogDirectory();
    }

    public function testAddPathAddsAPathToThePathsArrayAndRemovesDuplicates()
    {
        $config = Mockery::mock(
            Configuration::class . '[read,write]',
            [new Filesystem()]
        );

        $config->shouldReceive('read')->andReturn(
            [
                'paths' => ['path-1', 'path-2'],
            ]
        );

        $config->shouldReceive('write')->with(
            [
                'paths' => ['path-1', 'path-2', 'path-3'],
            ]
        );

        $config->addPath('path-3');

        $config =
            Mockery::mock(
                Configuration::class . '[read,write]',
                [new Filesystem()]
            );
        $config->shouldReceive('read')->andReturn(
            [
                'paths' => ['path-1', 'path-2', 'path-3'],
            ]
        );
        $config->shouldReceive('write')->with(
            [
                'paths' => ['path-1', 'path-2', 'path-3'],
            ]
        );
        $config->addPath('path-3');
    }

    public function testPathsMayBeRemovedFromTheConfiguration()
    {
        $config = Mockery::mock(
            Configuration::class . '[read,write]',
            [new Filesystem()]
        );

        $config->shouldReceive('read')->andReturn(
            [
                'paths' => ['path-1', 'path-2'],
            ]
        );

        $config->shouldReceive('write')->with(
            [
                'paths' => ['path-1'],
            ]
        );

        $config->removePath('path-2');
    }

    public function testPruneRemovesDirectoriesFromPathsThatNoLongerExist()
    {
        $files = Mockery::mock(Filesystem::class . '[exists,isDir]');
        swap(Filesystem::class, $files);

        $files->shouldReceive('exists')
            ->with(VALET_HOME_PATH . '/config.json')
            ->andReturn(true);

        $files->shouldReceive('isDir')->with('path-1')->andReturn(true);
        $files->shouldReceive('isDir')->with('path-2')->andReturn(false);

        $config = Mockery::mock(
            Configuration::class . '[read,write]',
            [$files]
        );

        $config->shouldReceive('read')->andReturn(
            [
                'paths' => ['path-1', 'path-2'],
            ]
        );

        $config->shouldReceive('write')->with(
            [
                'paths' => ['path-1'],
            ]
        );

        $config->prune();
    }

    public function testPruneDoesntExecuteIfConfigurationDirectoryDoesntExist()
    {
        $files = Mockery::mock(Filesystem::class . '[exists]');
        swap(Filesystem::class, $files);

        $files->shouldReceive('exists')
            ->with(VALET_HOME_PATH . '/config.json')
            ->andReturn(false);

        $config = Mockery::mock(
            Configuration::class . '[read,write]',
            [$files]
        );

        $config->shouldReceive('read')->never();
        $config->shouldReceive('write')->never();
        $config->prune();
    }

    public function testUpdateKeyUpdatesTheSpecifiedConfigurationKey()
    {
        $config = Mockery::mock(
            Configuration::class . '[read,write]',
            [new Filesystem()]
        );
        $config->shouldReceive('read')->once()->andReturn(['foo' => 'bar']);

        $config->shouldReceive('write')->once()->with(
            ['foo' => 'bar', 'bar' => 'baz']
        );

        $config->updateKey('bar', 'baz');
    }
}
