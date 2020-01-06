<?php

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Valet\PhpFpm;

class PhpFpmTest extends TestCase
{
    public function setUp(): void
    {
        $_SERVER['SUDO_USER'] = user();

        Container::setInstance(new Container());
    }

    public function tearDown(): void
    {
        exec('rm -rf ' . __DIR__ . '/output');
        mkdir(__DIR__ . '/output');
        touch(__DIR__ . '/output/.gitkeep');

        Mockery::close();
    }

    public function testFpmIsConfiguredWithTheCorrectUserGroupAndPort()
    {
        copy(__DIR__ . '/files/fpm.conf', __DIR__ . '/output/fpm.conf');
        mkdir(__DIR__ . '/output/conf.d');

        copy(
            __DIR__ . '/files/php-memory-limits.ini',
            __DIR__ . '/output/conf.d/php-memory-limits.ini'
        );

        resolve(StubForUpdatingFpmConfigFiles::class)->updateConfiguration();
        $contents = file_get_contents(__DIR__ . '/output/fpm.conf');

        $this->assertStringContainsString(
            sprintf("\nuser = %s", user()),
            $contents
        );

        $this->assertStringContainsString("\ngroup = staff", $contents);

        $this->assertStringContainsString(
            "\nlisten = " . VALET_HOME_PATH . "/valet.sock",
            $contents
        );
    }
}

class StubForUpdatingFpmConfigFiles extends PhpFpm
{
    public function fpmConfigPath()
    {
        return __DIR__ . '/output/fpm.conf';
    }
}
