<?php

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Valet\Configuration;
use Valet\Filesystem;
use Valet\Nginx;
use Valet\Site;

class NginxTest extends TestCase
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

    public function testInstallNginxConfigPlacesBaseConfigInProperLocation()
    {
        $files = Mockery::mock(Filesystem::class . '[putAsUser]');

        $files->shouldReceive('putAsUser')->andReturnUsing(
            function ($path, $contents) {
                $this->assertSame('/usr/local/etc/nginx/nginx.conf', $path);

                $this->assertTrue(
                    strpos(
                        $contents,
                        'include ' . VALET_HOME_PATH . '/Nginx/*'
                    ) !== false
                );
            }
        )->once();

        swap(Filesystem::class, $files);

        $nginx = resolve(Nginx::class);
        $nginx->installConfiguration();
    }

    public function testInstallCaddyDirsCreatesLocationForSiteConfiguration()
    {
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('isDir')
            ->with(VALET_HOME_PATH . '/Nginx')
            ->andReturn(false);

        $files->shouldReceive('mkdirAsUser')
            ->with(VALET_HOME_PATH . '/Nginx')
            ->once();

        $files->shouldReceive('putAsUser')->with(
            VALET_HOME_PATH . '/Nginx/.keep',
            "\n"
        )->once();

        swap(Filesystem::class, $files);
        swap(Configuration::class, Mockery::spy(Configuration::class));
        swap(Site::class, Mockery::spy(Site::class));

        $nginx = resolve(Nginx::class);
        $nginx->installNginxDirectory();
    }

    public function testNginxDirectoryIsNeverCreatedIfItAlreadyExists()
    {
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('isDir')
            ->with(VALET_HOME_PATH . '/Nginx')
            ->andReturn(true);

        $files->shouldReceive('mkdirAsUser')->never();
        $files->shouldReceive('putAsUser')->with(
            VALET_HOME_PATH . '/Nginx/.keep',
            "\n"
        )->once();

        swap(Filesystem::class, $files);
        swap(Configuration::class, Mockery::spy(Configuration::class));
        swap(Site::class, Mockery::spy(Site::class));

        $nginx = resolve(Nginx::class);
        $nginx->installNginxDirectory();
    }

    public function testInstallNginxDirectoriesRewritesSecureNginxFiles()
    {
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('isDir')
            ->with(VALET_HOME_PATH . '/Nginx')
            ->andReturn(false);

        $files->shouldReceive('mkdirAsUser')
            ->with(VALET_HOME_PATH . '/Nginx')
            ->once();

        $files->shouldReceive('putAsUser')->with(
            VALET_HOME_PATH . '/Nginx/.keep',
            "\n"
        )->once();

        swap(Filesystem::class, $files);

        swap(
            Configuration::class,
            $config =
                Mockery::spy(
                    Configuration::class,
                    ['read' => ['domain' => 'test']]
                )
        );

        swap(Site::class, $site = Mockery::spy(Site::class));

        $nginx = resolve(Nginx::class);
        $nginx->installNginxDirectory();

        $site->shouldHaveReceived('resecureForNewDomain', ['test', 'test']);
    }
}
