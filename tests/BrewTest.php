<?php

use Illuminate\Container\Container;
use Valet\Brew;
use Valet\CommandLine;
use Valet\Filesystem;
use Valet\Pecl as Pecl;
use Valet\PeclCustom as PeclCustom;
use Valet\PhpFpm as PhpFpm;

class BrewTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $_SERVER['SUDO_USER'] = user();

        Container::setInstance(new Container());
    }

    public function tearDown(): void
    {
        $container = \Mockery::getContainer();
        $this->addToAssertionCount($container->mockery_getExpectationCount());

        Mockery::close();
    }

    public function testBrewCanBeResolvedFromContainer()
    {
        $this->assertInstanceOf(Brew::class, resolve(Brew::class));
    }

    public function testInstalledReturnsTrueWhenGivenFormulaIsInstalled()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('brew list | grep php71')
            ->andReturn('php71');

        swap(CommandLine::class, $cli);
        $this->assertTrue(resolve(Brew::class)->installed('php71'));

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('brew list | grep php71')
            ->andReturn(
                'php71-mcrypt
php71'
            );

        swap(CommandLine::class, $cli);
        $this->assertTrue(resolve(Brew::class)->installed('php71'));
    }

    public function testInstalledReturnsFalseWhenGivenFormulaIsNotInstalled()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('brew list | grep php71')
            ->andReturn('');

        swap(CommandLine::class, $cli);
        $this->assertFalse(resolve(Brew::class)->installed('php71'));

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('brew list | grep php71')
            ->andReturn('php71-mcrypt');

        swap(CommandLine::class, $cli);
        $this->assertFalse(resolve(Brew::class)->installed('php71'));

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('brew list | grep php71')
            ->andReturn(
                'php71-mcrypt
php71-something-else
php7'
            );

        swap(CommandLine::class, $cli);
        $this->assertFalse(resolve(Brew::class)->installed('php71'));
    }

    public function testHasInstalledPhpIndicatesIfPhpIsInstalledViaBrew()
    {
        $command_line = new CommandLine();
        $filesystem = new Filesystem();

        /** @var Mockery\Mock|Valet\Brew $brew */
        $brew = Mockery::mock(
            Brew::class . '[installed]',
            [$command_line, $filesystem]
        );

        $peclCustom = new PeclCustom($command_line, $filesystem);
        $pecl = new Pecl($command_line, $filesystem, $peclCustom);

        /** @var \Mockery\Mock|PhpFpm $phpFpm */
        $phpFpm =
            new PhpFpm(
                $brew,
                $command_line,
                $filesystem,
                $pecl,
                $peclCustom
            );

        $brew->shouldReceive('installed')
            ->with('valet-php@7.2')
            ->andReturn(true);

        $brew->shouldReceive('installed')
            ->with('valet-php@7.3')
            ->andReturn(false);

        $brew->shouldReceive('installed')
            ->with('valet-php@7.4')
            ->andReturn(false);

        $this->assertTrue($phpFpm->hasInstalledPhp());

        $commandLine = new CommandLine();
        $filesystem = new Filesystem();

        /** @var Mockery\Mock|Valet\Brew $brew */
        $brew = Mockery::mock(
            Brew::class . '[installed]',
            [$command_line, $filesystem]
        );

        $peclCustom = new PeclCustom($command_line, $filesystem);
        $pecl = new Pecl($command_line, $filesystem, $peclCustom);

        /** @var \Mockery\Mock|PhpFpm $phpFpm */
        $phpFpm = new PhpFpm(
            $brew,
            $command_line,
            $filesystem,
            $pecl,
            $peclCustom
        );

        $brew->shouldReceive('installed')
            ->with('valet-php@7.2')
            ->andReturn(false);

        $brew->shouldReceive('installed')
            ->with('valet-php@7.3')
            ->andReturn(true);

        $brew->shouldReceive('installed')
            ->with('valet-php@7.4')
            ->andReturn(false);

        $this->assertTrue($phpFpm->hasInstalledPhp());

        $commandLine = new CommandLine();
        $filesystem = new Filesystem();

        /** @var Mockery\Mock|Valet\Brew $brew */
        $brew = Mockery::mock(
            Brew::class . '[installed]',
            [$command_line, $filesystem]
        );

        $peclCustom = new PeclCustom($command_line, $filesystem);
        $pecl = new Pecl($command_line, $filesystem, $peclCustom);

        /** @var \Mockery\Mock|PhpFpm $phpFpm */
        $phpFpm = new PhpFpm(
            $brew,
            $command_line,
            $filesystem,
            $pecl,
            $peclCustom
        );

        $brew->shouldReceive('installed')->with('valet-php@7.2')->andReturn(
            false
        );
        $brew->shouldReceive('installed')->with('valet-php@7.3')->andReturn(
            false
        );
        $brew->shouldReceive('installed')->with('valet-php@7.4')->andReturn(
            true
        );

        $this->assertTrue($phpFpm->hasInstalledPhp());
    }

    public function testTapTapsTheGivenHomebrewRepository()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('passthru')->once()->with(
            'sudo -u ' . user() . ' brew tap php71'
        );

        $cli->shouldReceive('passthru')->once()->with(
            'sudo -u ' . user() . ' brew tap php70'
        );

        $cli->shouldReceive('passthru')->once()->with(
            'sudo -u ' . user() . ' brew tap php56'
        );

        swap(CommandLine::class, $cli);
        resolve(Brew::class)->tap('php71', 'php70', 'php56');
    }

    public function testRestartRestartsTheServiceUsingHomebrewServices()
    {
        $cli = Mockery::mock(CommandLine::class);
        $cli->shouldReceive('runAsUser')->once()->with(
            'brew list | grep dnsmasq'
        )->andReturn('dnsmasq');

        $cli->shouldReceive('quietly')->once()->with(
            'sudo brew services stop dnsmasq'
        );

        $cli->shouldReceive('quietly')->once()->with(
            'sudo brew services start dnsmasq'
        );

        swap(CommandLine::class, $cli);
        resolve(Brew::class)->restartService('dnsmasq');
    }

    public function testStopStopsTheServiceUsingHomebrewServices()
    {
        $cli = Mockery::mock(CommandLine::class);
        $cli->shouldReceive('runAsUser')->once()->with(
            'brew list | grep dnsmasq'
        )->andReturn('dnsmasq');

        $cli->shouldReceive('quietly')->once()->with(
            'sudo brew services stop dnsmasq'
        );

        swap(CommandLine::class, $cli);
        resolve(Brew::class)->stopService('dnsmasq');
    }

    public function testLinkedPhpReturnsLinkedPhpFormulaName()
    {
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('isLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn(true);

        $files->shouldReceive('readLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn('/test/path/valet-php@7.2/test');

        swap(Filesystem::class, $files);
        $this->assertSame('7.2', resolve(PhpFpm::class)->linkedPhp(true));

        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('isLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn(true);

        $files->shouldReceive('readLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn('/test/path/valet-php@7.3/test');

        swap(Filesystem::class, $files);
        $this->assertSame('7.3', resolve(PhpFpm::class)->linkedPhp(true));
    }

    public function testLinkedPhpThrowsExceptionIfNoPhpLink()
    {
        $this->expectException(DomainException::class);
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('isLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn(false);

        swap(Filesystem::class, $files);
        resolve(PhpFpm::class)->linkedPhp();
    }

    public function testLinkedPhpThrowsExceptionIfUnsupportedPhpVersionLinked()
    {
        $this->expectException(DomainException::class);

        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('isLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn(true);

        $files->shouldReceive('readLink')
            ->once()
            ->with('/usr/local/bin/php')
            ->andReturn('/test/path/php42/test');

        swap(Filesystem::class, $files);
        resolve(PhpFpm::class)->linkedPhp();
    }

    public function testInstallOrFailWillInstallBrewFormulas()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')->once()->with(
            'brew install dnsmasq',
            Mockery::type('Closure')
        );

        swap(CommandLine::class, $cli);
        resolve(Brew::class)->installOrFail('dnsmasq');
    }

    public function testInstallOrFailCanInstallTaps()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')->once()->with(
            'brew install dnsmasq',
            Mockery::type('Closure')
        );

        swap(CommandLine::class, $cli);
        $brew = Mockery::mock(Brew::class . '[tap]', [$cli, new Filesystem()]);
        $brew->shouldReceive('tap')->once()->with(['test/tap']);
        $brew->installOrFail('dnsmasq', [], ['test/tap']);
    }

    public function testInstallOrFailThrowsExceptionOnFailure()
    {
        $this->expectException(DomainException::class);

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runAsUser')->andReturnUsing(
            function ($command, $onError) {
                $onError(1, 'test error ouput');
            }
        );

        swap(CommandLine::class, $cli);
        resolve(Brew::class)->installOrFail('dnsmasq');
    }
}
