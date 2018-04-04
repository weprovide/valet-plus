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

/**
 * Create the application.
 */
Container::setInstance(new Container);

// get current version based on git describe and tags
$version = new Version('0.0.0' , __DIR__ . '/../');

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
$app->command('install [--with-mariadb]', function ($withMariadb) {
    Nginx::stop();
    PhpFpm::stop();
    Mysql::stop();
    RedisTool::stop();
    DevTools::install();

    Configuration::install();
    Nginx::install();
    PhpFpm::install();
    DnsMasq::install();
    Mysql::install($withMariadb ? 'mariadb' : 'mysql');
    RedisTool::install();
    Mailhog::install();
    Nginx::restart();
    Valet::symlinkToUsersBin();
    Mysql::setRootPassword();

    output(PHP_EOL.'<info>Valet installed successfully!</info>');
})->descriptions('Install the Valet services');

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

        DnsMasq::updateDomain(
            $oldDomain = Configuration::read()['domain'], $domain = trim($domain, '.')
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
    $app->command('link [name] [--secure]', function ($name, $secure) {
        $domain = Site::link(getcwd(), $name = $name ?: basename(getcwd()));

        if ($secure) {
            $this->runCommand('secure '.$name);
        }

        info('Current working directory linked to '.$domain);
    })->descriptions('Link the current working directory to Valet');

    /**
     * Register a subdomain link with Valet.
     */
    $app->command('subdomain [action] [name] [--secure]', function ($action, $name, $secure) {
        if($action === 'list') {
            $links = Site::links(basename(getcwd()));

            table(['Site', 'SSL', 'URL', 'Path'], $links->all());
            return;
        }

        if($action === 'add') {
            $domain = Site::link(getcwd(), $name.'.'.basename(getcwd()));

            if ($secure) {
                $this->runCommand('secure '. $name);
            }

            info('Current working directory linked to '.$domain);
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
        Site::unlink($name = $name ?: basename(getcwd()));

        info('The ['.$name.'] symbolic link has been removed.');
    })->descriptions('Remove the specified Valet link');

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

        Site::unsecure($url);

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

        passthru("open ".escapeshellarg($url));
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
        if(empty($services)) {
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

        foreach($services as $service) {
            switch($service) {
                case 'nginx': {
                    Nginx::restart();
                    break;
                }
                case 'mysql':
                case 'mariadb': {
                    Mysql::restart();
                    break;
                }
                case 'php': {
                    PhpFpm::restart();
                    break;
                }
                case 'redis': {
                    RedisTool::restart();
                    break;
                }
                case 'mailhog': {
                    Mailhog::restart();
                    break;
                }
                case 'elasticsearch': {
                    Elasticsearch::restart();
                    break;
                }
                case 'rabbitmq': {
                    RabbitMq::restart();
                    break;
                }
                case 'varnish': {
                    Varnish::restart();
                    break;
                }
            }
        }

        info('Specified Valet services have been started.');
    })->descriptions('Start the Valet services');

    /**
     * Restart the daemon services.
     */
    $app->command('restart [services]*', function ($services) {
        if(empty($services)) {
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

        foreach($services as $service) {
            switch($service) {
                case 'nginx': {
                    Nginx::restart();
                    break;
                }
                case 'mysql': {
                    Mysql::restart();
                    break;
                }
                case 'php': {
                    PhpFpm::restart();
                    break;
                }
                case 'redis': {
                    RedisTool::restart();
                    break;
                }
                case 'mailhog': {
                    Mailhog::restart();
                    break;
                }
                case 'elasticsearch': {
                    Elasticsearch::restart();
                    break;
                }
                case 'rabbitmq': {
                    RabbitMq::restart();
                    break;
                }
                case 'varnish': {
                    Varnish::restart();
                    break;
                }
            }
        }

        info('Specified Valet services have been started.');
    })->descriptions('Restart the Valet services');

    /**
     * Stop the daemon services.
     */
    /**
     * Start the daemon services.
     */
    $app->command('stop [services]*', function ($services) {
        if(empty($services)) {
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

        foreach($services as $service) {
            switch($service) {
                case 'nginx': {
                    Nginx::stop();
                    break;
                }
                case 'mysql': {
                    Mysql::stop();
                    break;
                }
                case 'php': {
                    PhpFpm::stop();
                    break;
                }
                case 'redis': {
                    RedisTool::stop();
                    break;
                }
                case 'mailhog': {
                    Mailhog::stop();
                    break;
                }
                case 'elasticsearch': {
                    Elasticsearch::stop();
                    break;
                }
                case 'rabbitmq': {
                    RabbitMq::stop();
                    break;
                }
                case 'varnish': {
                    Varnish::stop();
                    break;
                }
            }
        }

        info('Specified Valet services have been stopped.');
    })->descriptions('Stop the Valet services');

    /**
     * Uninstall Valet entirely.
     */
    $app->command('uninstall', function () {
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
     * Switch between versions of PHP
     */
    $app->command('use [phpVersion]', function ($phpVersion) {
        $switched = PhpFpm::switchTo($phpVersion);

        if(!$switched) {
            info('Already on this version');
            return;
        }
        info('Valet is now using php@'.$phpVersion.'.');
    })->descriptions('Switch between versions of PHP');

    /**
     * Fix common problems
     */
    $app->command('fix', function () {
        PhpFpm::fix();
    })->descriptions('Switch between versions of PHP');

    /**
     * Create database
     */
    $app->command('db [run] [name] [optional] [-y|--yes]', function ($input, $output, $run, $name, $optional) {
        $helper = $this->getHelperSet()->get('question');
        $defaults = $input->getOptions();

        if($run === 'list' || $run === 'ls') {
            Mysql::listDatabases();
            return;
        }

        if($run === 'create') {
            $databaseName = Mysql::createDatabase($name);

            if(!$databaseName) {
                return warning('Error creating database');
            }

            info('Database "' . $databaseName . '" created successfully');
            return;
        }

        if($run === 'drop') {
            if(!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to delete the database? [y/N] ', FALSE);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }
            $databaseName = Mysql::dropDatabase($name);

            if(!$databaseName) {
                return warning('Error dropping database');
            }

            info('Database "' . $databaseName . '" dropped successfully');
            return;
        }

        if($run === 'reset') {
          if(!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to reset the database? [y/N] ', FALSE);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }

            $dropped = Mysql::dropDatabase($name);

            if(!$dropped) {
                return warning('Error creating database');
            }

            $databaseName = Mysql::createDatabase($name);

            if(!$databaseName) {
                return warning('Error creating database');
            }

            info('Database "' . $databaseName . '" reset successfully');
            return;
        }

        if($run === 'open') {
            if($name === '.') {
                $name = basename(getcwd());
            }

            info('Opening database...');

            Mysql::openSequelPro($name);
            return;
        }

        if($run === 'import') {
            info('Importing database...');
            if(!$name) {
                throw new Exception('Please provide a dump file');
            }
            Mysql::importDatabase($name, $optional);
            return;
        }

        if($run === 'reimport') {
          if(!$defaults['yes']) {
                $question = new ConfirmationQuestion('Are you sure you want to reimport the database? [y/N] ', FALSE);
                if (!$helper->ask($input, $output, $question)) {
                    warning('Aborted');
                    return;
                }
            }
            info('Resetting database, importing database...');
            if(!$name) {
                throw new Exception('Please provide a dump file');
            }
            Mysql::reimportDatabase($name, $optional);
            return;
        }

        if($run === 'export' || $run === 'dump') {
            info('Exporting database...');
            $data = Mysql::exportDatabase($name, $optional);
            info('Database "' . $data['database'] . '" exported into file "' . $data['filename'] . '"');
            return;
        }

        throw new Exception('Command not found');
    })->descriptions('Database commands (list/ls, create, drop, reset, open, import, reimport, export/dump)');

    $app->command('configure', function () {
        DevTools::configure();
    })->descriptions('Configure application connection settings');

    $app->command('xdebug [mode] [--remote_autostart=]', function ($input, $mode) {
        $restart = false;
        $isValidMode = false;
        $defaults = $input->getOptions();
        if (isset($defaults['remote_autostart'])) {
            if ($defaults['remote_autostart']) {
                PhpFpm::enableAutoStart();
            } else {
                PhpFpm::disableAutoStart();
            }
            $restart = true;
        }

        if ($mode == '' || $mode == 'status') {
            PhpFpm::isExtensionEnabled('xdebug');
            $isValidMode = true;
        }

        if ($mode === 'on' || $mode === 'enable') {
            $change = PhpFpm::enableExtension('xdebug');
            if ($change) {
                $restart = true;
            }
            $isValidMode = true;
        }

        if ($mode === 'off' || $mode === 'disable') {
            $change = PhpFpm::disableExtension('xdebug');
            if ($change) {
                $restart = true;
            }
            $isValidMode = true;
        }

        if ($restart) {
            PhpFpm::restart();
            return;
        }

        if (!$isValidMode) {
            throw new Exception('Mode not found. Available modes: on / off / status');
        }

        return;
    })->descriptions('Enable / disable Xdebug');

    $app->command('ioncube [mode]', function ($mode) {
        if ($mode == '' || $mode == 'status') {
            PhpFpm::isExtensionEnabled('ioncubeloader');
            return;
        }

        if ($mode === 'on' || $mode === 'enable') {
            PhpFpm::enableExtension('ioncubeloader');
            PhpFpm::restart();
            return;
        }

        if ($mode === 'off' || $mode === 'disable') {
            PhpFpm::disableExtension('ioncubeloader');
            PhpFpm::restart();
            return;
        }

        throw new Exception('Mode not found. Available modes: on / off / status');
    })->descriptions('Enable / disable ioncube');

    $app->command('elasticsearch [mode]', function ($mode) {
        if($mode === 'install' || $mode === 'on') {
            Elasticsearch::install();
            return;
        }

        throw new Exception('Sub-command not found. Available: install');
    })->descriptions('Enable / disable Elasticsearch');

    $app->command('rabbitmq [mode]', function ($mode) {
        if($mode === 'install' || $mode === 'on') {
            RabbitMq::install();
            return;
        }

        throw new Exception('Sub-command not found. Available: install');
    })->descriptions('Enable / disable RabbitMq');

    $app->command('varnish [mode]', function ($mode) {
        if($mode === 'install' || $mode === 'on') {
            Varnish::install();
            return;
        }

        throw new Exception('Sub-command not found. Available: install');
    })->descriptions('Enable / disable Varnish');

    $app->command('tower', function () {
        DevTools::tower();
    })->descriptions('Open closest git project in Tower');

    $app->command('phpstorm', function () {
        DevTools::phpstorm();
    })->descriptions('Open closest git project in PHPstorm');

    $app->command('vscode', function () {
        DevTools::vscode();
    })->descriptions('Open closest git project in Visual Studio Code');

    $app->command('ssh-key', function () {
        DevTools::sshkey();
    })->descriptions('Copy ssh key');
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
