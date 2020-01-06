<?php

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Valet\Configuration;
use Valet\Filesystem;
use Valet\Site;

class SiteTest extends TestCase
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

    public function testSymlinkCreatesSymlinkToGivenPath()
    {
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('ensureDirExists')->once()->with(
            VALET_HOME_PATH . '/Sites',
            user()
        );

        $config = Mockery::mock(Configuration::class);

        $config->shouldReceive('prependPath')->once()->with(
            VALET_HOME_PATH . '/Sites'
        );

        $files->shouldReceive('symlinkAsUser')->once()->with(
            'target',
            VALET_HOME_PATH . '/Sites/link'
        );
        
        $domain = 'example.com';
        $config->shouldReceive('read')->andReturn(['domain' => $domain]);

        swap(Filesystem::class, $files);
        swap(Configuration::class, $config);

        $linkPath = resolve(Site::class)->link('target', 'link');
        $this->assertSame("link.{$domain}", $linkPath);
    }

    public function testUnlinkRemovesExistingSymlink()
    {
        file_put_contents(__DIR__ . '/output/file.out', 'test');
        symlink(__DIR__ . '/output/file.out', __DIR__ . '/output/link');
        $site = resolve(StubForRemovingLinks::class);
        $site->unlink('link');
        $this->assertFileNotExists(__DIR__ . '/output/link');

        $site = resolve(StubForRemovingLinks::class);
        $site->unlink('link');
        $this->assertFileNotExists(__DIR__ . '/output/link');
    }

    public function testPruneLinksRemovesBrokenSymlinksInSitesPath()
    {
        file_put_contents(__DIR__ . '/output/file.out', 'test');
        symlink(__DIR__ . '/output/file.out', __DIR__ . '/output/link');
        unlink(__DIR__ . '/output/file.out');
        $site = resolve(StubForRemovingLinks::class);
        $site->pruneLinks();
        $this->assertFileNotExists(__DIR__ . '/output/link');
    }
}

class StubForRemovingLinks extends Site
{
    public function sitesPath()
    {
        return __DIR__ . '/output';
    }
}
