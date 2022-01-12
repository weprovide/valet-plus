#!/usr/bin/env php
<?php

/**
 * Load correct autoloader depending on install location.
 */
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} else {
    require __DIR__.'/../../../autoload.php';
}

use Silly\Application;
use Illuminate\Container\Container;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use SebastianBergmann\Version;
use Symfony\Component\Console\Question\Question;

/**
 * Create the application.
 */
Container::setInstance(new Container);

// get current version based on git describe and tags
$version = new Version('2.3.0', __DIR__ . '/../');

$app = new Application('Valet+', $version->getVersion());

/**
 * Prune missing directories and symbolic links on every command.
 */
if (is_dir(VALET_HOME_PATH)) {
    Configuration::prune();
    Site::pruneLinks();
}

/**
 * Allow Valet to be run more conveniently by allowing the Node proxy to run password-less sudo.
 */
$app->command('install [--with-mariadb] [--with-mysql-8]', function ($withMariadb, $withMysql8) {
    if ($withMariadb && $withMysql8) {
        throw new Exception('Cannot install Valet+ with both MariaDB and Mysql8, please pick one.');
    }
    $dbVersion = $withMariadb ? 'mariadb' : 'mysql@5.7';
    $dbVersion = $withMysql8 ? 'mysql' : $dbVersion;

    Nginx::stop();
    PhpFpm::stop();
    Mysql::stop();
    RedisTool::stop();
    DevTools::install();
    Binaries::installBinaries();

    Configuration::install();
    $domain = Nginx::install();
    PhpFpm::install();
    DnsMasq::install();
    Mysql::install($dbVersion);
    RedisTool::install();
    Mailhog::install();
    Nginx::restart();
    Valet::symlinkToUsersBin();
    Mysql::setRootPassword();

    Mailhog::updateDomain($domain);
    Elasticsearch::updateDomain($domain);

    output(PHP_EOL.'<info>Valet installed successfully!</info>');
})->descriptions('Install the Valet services');

/**
 * Fix common problems within the Valet+ installation.
 */
$app->command('fix [--reinstall]', function ($reinstall) {
    if (file_exists($_SERVER['HOME'] . '/.my.cnf')) {
        warning('You have an .my.cnf file in your home directory. This can affect the mysql installation negatively.');
    }

    PhpFpm::fix($reinstall);
    Pecl::fix();
})->descriptions('Fixes common installation problems that prevent Valet+ from working');

/**
 * Most commands are available only if valet is installed.
 */
if (is_dir(VALET_HOME_PATH)) {
    /**
     * Get or set the domain currently being used by Valet.
     */
    $app->command('domain [domain]', function ($domain = null) {
        if ($domain === null) {
            return info(Configuration::read()['domain']);
        }

        Mailhog::updateDomain($domain);
        Elasticsearch::updateDomain($domain);

        DnsMasq::updateDomain(
            $oldDomain = Configuration::read()['domain'],
            $domain = trim($domain, '.')
        );

        Configuration::updateKey('domain', $domain);

        Site::resecureForNewDomain($oldDomain, $domain);
        PhpFpm::restart();
        Nginx::restart();

        info('Your Valet domain has been updated to ['.$domain.'].');
    })->descriptions('Get or set the domain used for Valet sites');

    /**
     * Add the current working directory to the paths configuration.
     */
    $app->command('park [path]', function ($path = null) {
        Configuration::addPath($path ?: getcwd());

        info(($path === null ? "This" : "The [{$path}]") . " directory has been added to Valet's paths.");
    })->descriptions('Register the current working (or specified) directory with Valet');

    /**
     * Remove the current working directory from the paths configuration.
     */
    $app->command('forget [path]', function ($path = null) {
        Configuration::removePath($path ?: getcwd());

        info(($path === null ? "This" : "The [{$path}]") . " directory has been removed from Valet's paths.");
    })->descriptions('Remove the current working (or specified) directory from Valet\'s list of paths');

    /**
     * Register a symbolic link with Valet.
     */
    $app->command('link [name] [--secure] [--proxy]', function ($name, $secure, $proxy) {
        $domain = Site::link(getcwd(), $name = $name ?: basename(getcwd()));

        if ($secure) {
            $this->runCommand('secure '.$name);
        }

        if ($proxy) {
            $this->runCommand('proxy '.$name);
        }

        info('Current working directory linked to '.$domain);
    })->descriptions('Link the current working directory to Valet');

    /**
     * Register a subdomain link with Valet.
     */
    $app->command('subdomain [action] [name] [--secure] [--proxy]', function ($action, $name, $secure, $proxy) {
        if ($action === 'list') {
            $links = Site::links(basename(getcwd()));

            table(['Site', 'SSL', 'URL', 'Path'], $links->all());
            return;
        }

        if ($action === 'add') {
            $domain = Site::link(getcwd(), $name.'.'.basename(getcwd()));

            if ($secure) {
                $this->runCommand('secure '. $name);
            }

            if ($proxy) {
                $this->runCommand('proxy '.$name);
            }

            info('Current working directory linked to '.$domain);
            return;
        }

        if ($action === 'remove') {
            Site::unlink($name.'.'.basename(getcwd()));

            info('Current working directory unlinked from '.$name.'.'.basename(getcwd()));
            return;
        }

        throw new DomainException('Specified command not found');
    })->descriptions('Manage subdomains');

    /**
     * Display all of the registered symbolic links.
     */
    $app->command('links', function () {
        $links = Site::links();

        table(['Site', 'SSL', 'URL', 'Path'], $links->all());
    })->descriptions('Display all of the registered Valet links');

    /**
     * Unlink a link from the Valet links directory.
     */
    $app->command('unlink [name]', function ($name) {
        if (!Site::unlink($name = $name ?: basename(getcwd()))) {
            warning('Error unlinking, make sure the link exists by running `valet links` and do not include `.'. Configuration::read()['domain'] .'`');
        } else {
            info('The ['.$name.'] symbolic link has been removed.');
        }
    })->descriptions('Remove the specified Valet link, do not include `.'. Configuration::read()['domain'] .'`, ie: to unlink name.'. Configuration::read()['domain'] .' run: `valet unlink name`.');

    /**
     * Secure the given domain with a trusted TLS certificate.
     */
    $app->command('secure [domain]', function ($domain = null) {
        $url = ($domain ?: Site::host(getcwd())).'.'.Configuration::read()['domain'];

        Site::secure($url);

        PhpFpm::restart();

        Nginx::restart();

        info('The ['.$url.'] site has been secured with a fresh TLS certificate.');
    })->descriptions('Secure the given domain with a trusted TLS certificate');

    /**
     * Stop serving the given domain over HTTPS and remove the trusted TLS certificate.
     */
    $app->command('unsecure [domain]', function ($domain = null) {

        $url = ($domain ?: Site::host(getcwd())).'.'.Configuration::read()['domain'];

        $proxied = Site::proxied($url);
        Site::unsecure($url);
        if ($proxied) {
            Site::proxy($url, $proxied);
        }

        PhpFpm::restart();

        Nginx::restart();

        info('The ['.$url.'] site will now serve traffic over HTTP.');
    })->descriptions('Stop serving the given domain over HTTPS and remove the trusted TLS certificate');

    /**
     * Determine which Valet driver the current directory is using.
     */
    $app->command('which', function () {
        require __DIR__.'/drivers/require.php';

        $driver = ValetDriver::assign(getcwd(), basename(getcwd()), '/', true);

        if ($driver) {
            info('This site is served by ['.get_class($driver).'].');
        } else {
            warning('Valet could not determine which driver to use for this site.');
        }
    })->descriptions('Determine which Valet driver serves the current working directory');

    /**
     * Display all of the registered paths.
     */
    $app->command('paths', function () {
        $paths = Configuration::read()['paths'];

        if (count($paths) > 0) {
            output(json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            info('No paths have been registered.');
        }
    })->descriptions('Get all of the paths registered with Valet');

    /**
     * Open the current or given directory in the browser.
     */
    $app->command('open [domain]', function ($domain = null) {
        $url = "http://".($domain ?: Site::host(getcwd())).'.'.Configuration::read()['domain'];

        passthru("sudo -u ".user(). " open ".escapeshellarg($url));
    })->descriptions('Open the site for the current (or specified) directory in your browser');

    /**
     * Generate a publicly accessible URL for your project.
     */
    $app->command('share', function () {
        warning("It looks like you are running `cli/valet.php` directly, please use the `valet` script in the project root instead.");
    })->descriptions('Generate a publicly accessible URL for your project');

    /**
     * Echo the currently tunneled URL.
     */
    $app->command('fetch-share-url', function () {
        output(Ngrok::currentTunnelUrl());
    })->descriptions('Get the URL to the current Ngrok tunnel');

    /**
     * Start the daemon services.
     */
    $app->command('start [services]*', function ($services) {
        $phpVersion = false;

        if (!empty($services)) {
            // Check if services contains a php version so we can switch to it immediately.
            $phpVersions = array_keys(\Valet\PhpFpm::SUPPORTED_PHP_FORMULAE);
            $intersect   = array_intersect($services, $phpVersions);
            $phpVersion  = end($intersect);
            $services    = array_diff($services, $phpVersions);
        }

        if (empty($services)) {
            DnsMasq::restart();
            if ($phpVersion) {
                PhpFpm::switchTo($phpVersion);
            } else {
                PhpFpm::restart();
            }
            Nginx::restart();
            Mysql::restart();
            RedisTool::restart();
            Mailhog::restart();
            Elasticsearch::restart();
            RabbitMq::restart();
            Varnish::restart();
            info('Valet services have been started.');

            return;
        }

        foreach ($services as $service) {
            switch ($service) {
                case 'nginx':
                    Nginx::restart();
                    break;
                case 'mysql':
                case 'mariadb':
                    Mysql::restart();
                    break;
                case 'php':
                    if ($phpVersion) {
                        PhpFpm::switchTo($phpVersion);
                    } else {
                        PhpFpm::restart();
                    }
                    break;
                case 'redis':
                    RedisTool::restart();
                    break;
                case 'mailhog':
                    Mailhog::restart();
                    break;
                case 'elasticsearch':
                    Elasticsearch::restart();
                    break;
                case 'rabbitmq':
                    RabbitMq::restart();
                    break;
                case 'varnish':
                    Varnish::restart();
                    break;
            }
        }

        info('Specified Valet services have been started.');
    })->descriptions('Start the Valet services');

    /**
     * Restart the daemon services.
     */
    $app->command('restart [services]*', function ($services) {

        if (empty($services)) {
            DnsMasq::restart();
            PhpFpm::restart();
            Nginx::restart();
            Mysql::restart();
            RedisTool::restart();
            Mailhog::restart();
            Elasticsearch::restart();
            RabbitMq::restart();
            Varnish::restart();
            info('Valet services have been started.');
            return;
        }

        foreach ($services as $service) {
            switch ($service) {
                case 'nginx':
                    Nginx::restart();
                    break;
                case 'mysql':
                    Mysql::restart();
                    break;
                case 'php':
                    PhpFpm::restart();
                    break;
                case 'redis':
                    RedisTool::restart();
                    break;
                case 'mailhog':
                    Mailhog::restart();
                    break;
                case 'elasticsearch':
                    Elasticsearch::restart();
                    break;
                case 'rabbitmq':
                    RabbitMq::restart();
                    break;
                case 'varnish':
                    Varnish::restart();
                    break;
            }
        }

        info('Specified Valet services have been started.');
    })->descriptions('Restart the Valet services');

    /**
     * Stop the daemon services.
     */
    $app->command('stop [services]*', function ($services) {
        if (empty($services)) {
            DnsMasq::stop();
            PhpFpm::stop();
            Nginx::stop();
            Mysql::stop();
            RedisTool::stop();
            Mailhog::stop();
            Elasticsearch::stop();
            RabbitMq::stop();
            Varnish::stop();
            info('Valet services have been stopped.');
            return;
        }

        foreach ($services as $service) {
            switch ($service) {
                case 'nginx':
                    Nginx::stop();
                    break;
                case 'mysql':
                    Mysql::stop();
                    break;
                case 'php':
                    PhpFpm::stop();
                    break;
                case 'redis':
                    RedisTool::stop();
                    break;
                case 'mailhog':
                    Mailhog::stop();
                    break;
                case 'elasticsearch':
                    Elasticsearch::stop();
                    break;
                case 'rabbitmq':
                    RabbitMq::stop();
                    break;
                case 'varnish':
                    Varnish::stop();
                    break;
            }
        }

        info('Specified Valet services have been stopped.');
    })->descriptions('Stop the Valet services');

    /**
     * Uninstall Valet entirely.
     */
    $app->command('uninstall', function () {
        Binaries::uninstallBinaries();
        Pecl::uninstallExtensions();
        PeclCustom::uninstallExtensions();
        DevTools::uninstall();
        Nginx::uninstall();
        Mysql::uninstall();
        RedisTool::uninstall();
        Mailhog::uninstall();
        Elasticsearch::uninstall();
        RabbitMq::uninstall();
        Varnish::uninstall();

        info('Valet has been uninstalled.');
    })->descriptions('Uninstall the Valet services');

    /**
     * Determine if this is the latest release of Valet.
     */
    $app->command('on-latest-version', function () use ($version) {
        if (Valet::onLatestVersion($version)) {
            output('YES');
        } else {
            output('NO');
        }
    })->descriptions('Determine if this is the latest version of Valet');

    /**
     * Switch between versions of PHP (Default) or Elasticsearch
     */
    $app->command('use [service] [targetVersion]', function ($service, $targetVersion) {
        $supportedServices = [
            'php'           => 'php',
            'elasticsearch' => 'elasticsearch',
            'es'            => 'elasticsearch',
        ];
        if (is_numeric($service)) {
            $targetVersion = $service;
            $service       = 'php';
        }
        $service = (isset($supportedServices[$service]) ? $supportedServices[$service] : false);

        switch ($service) {
            case 'php':
                PhpFpm::switchTo($targetVersion);
                break;
            case 'elasticsearch':
                Elasticsearch::switchTo($targetVersion);
                break;
            default:
                throw new Exception('Service to switch version of not supported. Supported services: ' . implode(', ', array_unique(array_values($supportedServices))));
        }
    })->descriptions('Switch between versions of PHP (default) or Elasticsearch');

    /**
     * Create database
     */
    $app->command('db [run] [name] [optional] [-y|--yes]', function ($input, $output, $run, $name, $optional) {
        $helper = $this->getHelperSet()->get('question');
        $defaults = $input->getOptions();

        if ($run === 'list' || $run === 'ls') {
            Mysql::listDatabases();
            return;
        }

        if ($run === 'create') {
            $databaseName = Mysql::createDatabase($name);

            if (!$databaseName) {
                return warning('Error creating database');
            }

            info('Database "' . $databaseName . '" created successfully');
            return;
        }

        if ($run === 'drop') {
            if (!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to delete the database? [y/N] ', false);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }
            $databaseName = Mysql::dropDatabase($name);

            if (!$databaseName) {
                return warning('Error dropping database');
            }

            info('Database "' . $databaseName . '" dropped successfully');
            return;
        }

        if ($run === 'reset') {
            if (!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to reset the database? [y/N] ', false);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }

            $dropped = Mysql::dropDatabase($name);

            if (!$dropped) {
                return warning('Error creating database');
            }

            $databaseName = Mysql::createDatabase($name);

            if (!$databaseName) {
                return warning('Error creating database');
            }

            info('Database "' . $databaseName . '" reset successfully');
            return;
        }

        if ($run === 'open') {
            if ($name === '.') {
                $name = basename(getcwd());
            }

            info('Opening database...');

            Mysql::openSequelPro($name);
            return;
        }

        if ($run === 'import') {
            info('Importing database...');
            if (!$name) {
                throw new Exception('Please provide a dump file');
            }

            // check if database already exists.
            if (Mysql::isDatabaseExists($optional)) {
                $question = new ConfirmationQuestion('Database already exists are you sure you want to continue? [y/N] ', false);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }

            Mysql::importDatabase($name, $optional);
            return;
        }

        if ($run === 'reimport') {
            if (!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to reimport the database? [y/N] ', false);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }
            info('Resetting database, importing database...');
            if (!$name) {
                throw new Exception('Please provide a dump file');
            }
            Mysql::reimportDatabase($name, $optional);
            return;
        }

        if ($run === 'export' || $run === 'dump') {
            info('Exporting database...');
            $data = Mysql::exportDatabase($name, $optional);
            info('Database "' . $data['database'] . '" exported into file "' . $data['filename'] . '"');
            return;
        }

        if ($run === 'pwd' || $run === 'password') {
            if (!$name || !$optional) {
                throw new Exception('Missing arguments to change root user password. Use: "valet db pwd <old> <new>"');
            }

            info('Setting password for root user...');
            Mysql::setRootPassword($name, $optional);
            return;
        }

        throw new Exception('Command not found');
    })->descriptions('Database commands (list/ls, create, drop, reset, open, import, reimport, export/dump)');

    $app->command('configure', function () {
        DevTools::configure();
    })->descriptions('Configure application connection settings');

    $app->command('xdebug [mode] [--remote_autostart=]', function ($input, $mode) {
        $modes = ['on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
        }

        $restart = false;

        if (Pecl::isInstalled('xdebug') === false) {
            info('[PECL] Xdebug not found, installing...');
            Pecl::installExtension('xdebug');
            PhpFpm::installXdebugConfiguration();
            $restart = true;
        }

        $defaults = $input->getOptions();
        if (isset($defaults['remote_autostart'])) {
            if ($defaults['remote_autostart']) {
                PhpFpm::enableAutoStart();
            } else {
                PhpFpm::disableAutoStart();
            }
            $restart = true;
        }

        if (Pecl::isEnabled('xdebug') === false && ($mode === 'on' || $mode === 'enable')) {
            info("[PECL] Enabling xdebug extension");
            $restart = true;
            Pecl::enable('xdebug');
        } elseif ($mode === 'on' || $mode === 'enable') {
            info("[PECL] Xdebug extension is already enabled!");
        }

        if (Pecl::isEnabled('xdebug') === true && ($mode === 'off' || $mode === 'disable')) {
            info("[PECL] Disabling xdebug extension");
            $restart = true;
            Pecl::disable('xdebug');
        } elseif ($mode === 'off' || $mode === 'disable') {
            info("[PECL] Xdebug extension is already uninstalled!");
        }

        if ($restart) {
            PhpFpm::restart();
        }
    })->descriptions('Enable / disable Xdebug');

    $app->command('ioncube [mode]', function ($mode) {
        $modes = ['on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        if (PeclCustom::isInstalled('ioncube_loader_mac') === false) {
            info('[PECL-CUSTOM] Ioncube loader not found, installing...');
            PeclCustom::installExtension('ioncube_loader_mac');
        }

        if (PeclCustom::isEnabled('ioncube_loader_mac') === false && ($mode === 'on' || $mode === 'enable')) {
            info("[PECL-CUSTOM] Enabling ioncube_loader_mac extension");
            PeclCustom::enableExtension('ioncube_loader_mac');
            PhpFpm::restart();
        } elseif ($mode === 'on' || $mode === 'enable') {
            info("[PECL-CUSTOM] ioncube_loader_mac extension is already installed");
        }

        if (PeclCustom::isEnabled('ioncube_loader_mac') === true && ($mode === 'off' || $mode === 'disable')) {
            info("[PECL-CUSTOM] Disabling ioncube_loader_mac extension");
            PeclCustom::disable('ioncube_loader_mac');
            PhpFpm::restart();
        } elseif ($mode === 'off' || $mode === 'disable') {
            info("[PECL-CUSTOM] ioncube_loader_mac extension is already uninstalled");
        }
    })->descriptions('Enable / disable ioncube');

    $app->command('elasticsearch [mode]', function ($mode) {
        if ($mode === 'install' || $mode === 'on') {
            Elasticsearch::install();
            return;
        }

        throw new Exception('Sub-command not found. Available: install');
    })->descriptions('Enable / disable Elasticsearch');

    $app->command('rabbitmq [mode]', function ($mode) {
        $modes = ['install', 'on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        switch ($mode) {
            case 'install':
                RabbitMq::install();
                return;
            case 'enable':
            case 'on':
                RabbitMq::enable();
                return;
            case 'disable':
            case 'off':
                RabbitMq::disable();
                return;
        }
    })->descriptions('Enable / disable RabbitMq');

    $app->command('varnish [mode]', function ($mode) {
        $modes = ['install', 'on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        switch ($mode) {
            case 'install':
                Varnish::install();
                return;
            case 'enable':
            case 'on':
                Varnish::enable();
                return;
            case 'disable':
            case 'off':
                Varnish::disable();
                return;
        }
    })->descriptions('Enable / disable Varnish');

    $app->command('mailhog [mode]', function ($mode) {
        $modes = ['install', 'on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        switch ($mode) {
            case 'install':
                Mailhog::install();
                return;
            case 'enable':
            case 'on':
                Mailhog::enable();
                return;
            case 'disable':
            case 'off':
                Mailhog::disable();
                return;
        }
    })->descriptions('Enable / disable Mailhog');

    $app->command('redis [mode]', function ($mode) {
        $modes = ['install', 'on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        switch ($mode) {
            case 'install':
                RedisTool::install();
                return;
            case 'enable':
            case 'on':
                RedisTool::enable();
                return;
            case 'disable':
            case 'off':
                RedisTool::disable();
                return;
        }
    })->descriptions('Enable / disable Redis');

    $app->command('memcache [mode]', function ($mode) {
        $modes = ['install', 'uninstall'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: '.implode(', ', $modes));
        }

        if (PhpFpm::linkedPhp() == '5.6') {
            throw new Exception('Memcache needs php 7.0 or higher, current php version: 5.6');
        }

        $restart = false;
        switch ($mode) {
            case 'install':
                $restart = Memcache::install();
                break;
            case 'uninstall':
                $restart = Memcache::uninstall();
                break;
        }
        if ($restart) {
            PhpFpm::restart();
        }
    })->descriptions('Install / uninstall Memcache');

    $app->command('tower', function () {
        DevTools::tower();
    })->descriptions('Open closest git project in Tower');

    $app->command('phpstorm', function () {
        DevTools::phpstorm();
    })->descriptions('Open closest git project in PHPstorm');

    $app->command('sourcetree', function () {
        DevTools::sourcetree();
    })->descriptions('Open closest git project in SourceTree');

    $app->command('vscode', function () {
        DevTools::vscode();
    })->descriptions('Open closest git project in Visual Studio Code');

    $app->command('ssh-key', function () {
        DevTools::sshkey();
    })->descriptions('Copy ssh key');

    /**
     * Proxy commands
     */
    $app->command('proxy [url]', function ($input, $output, $url = null) {
        $url = ($url ?: Site::host(getcwd())).'.'.Configuration::read()['domain'];
        $helper = $this->getHelperSet()->get('question');
        $question = new Question('Where would you like to proxy this url to? ');
        if (!$to = $helper->ask($input, $output, $question)) {
            warning('Aborting, url is required');
            return;
        }

        Site::proxy($url, $to);

        PhpFpm::restart();
        Nginx::restart();

        info("The [$url] will now proxy traffic to [$to].");
    })->descriptions('Enable proxying for a site instead of handling it with a Valet driver. Useful for SPAs and Swoole applications.');

    $app->command('unproxy [url]', function ($url = null) {
        $url = ($url ?: Site::host(getcwd())).'.'.Configuration::read()['domain'];
        Site::proxy($url);

        PhpFpm::restart();
        Nginx::restart();

        info("The [$url] will no longer proxy traffic and will use the Valet driver instead.");
    })->descriptions('Disable proxying for a site re-instating handling with a Valet driver.');

    /**
     * Rewrite commands
     */
    $app->command('rewrite [url]', function ($url = null) {
        $host = Site::host(getcwd());

        if (!$url) {
            warning('Aborting, url is required');
            return;
        }

        $url = Site::rewrite($url, $host);
        if ($url === false) {
            warning('Aborting, url rewrite failed, might already exist');
            return;
        }

        info("The [$url] will now rewrite traffic to [$host].");
    })->descriptions('Rewrite any URL to your local site instance.');

    $app->command('unrewrite [url]', function ($url = null) {
        if (!$url) {
            warning('Aborting, url is required');
            return;
        }

        $url = Site::unrewrite($url);
        if ($url === false) {
            warning('Aborting, url unrewrite failed, might not exist');
            return;
        }

        info("The [$url] will no longer rewrite traffic.");
    })->descriptions('Remove a rewrite of an URL to your local site instance.');

    $app->command('rewrites', function () {
        $rewrites = Site::rewrites();

        table(['Site', 'URL'], $rewrites->all());
    })->descriptions('Display all of the registered Valet rewrites');

    $app->command('logs [service]', function ($service) {
        $logs = [
            'php' => '$HOME/.valet/Log/php.log',
            'php-fpm' => Architecture::getBrewPath() . '/var/log/php-fpm.log',
            'nginx' => '$HOME/.valet/Log/nginx-error.log',
            'mysql' => '$HOME/.valet/Log/mysql.log',
            'mailhog' => Architecture::getBrewPath() . '/var/log/mailhog.log',
            'redis' => Architecture::getBrewPath() . '/var/log/redis.log',
        ];

        if (!isset($logs[$service])) {
            warning('No logs found for [' . $service . ']. Available logs: '.implode(', ', array_keys($logs)));
            return;
        }

        $path = $logs[$service];
        if (!Logs::exists($path)) {
            warning('The path `' . $path . '` does not (yet) exists');
            return;
        }

        Logs::open($path);
    })->descriptions('Open the logs for the specified service. (php, php-fpm, nginx, mysql, mailhog, redis)');
}

/**
 * Load all of the Valet extensions.
 */
foreach (Valet::extensions() as $extension) {
    include $extension;
}

/**
 * Run the application.
 */
$app->run();
