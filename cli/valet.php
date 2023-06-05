#!/usr/bin/env php
<?php

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function Valet\info;
use function Valet\table;
use function Valet\warning;


// Require Laravel's Valet.
require_once __DIR__ . '/../vendor/laravel/valet/cli' . '/app.php';
require_once __DIR__ . '/includes/extends.php';


// Change name and version.
$app->setName('Valet+');
$app->setVersion('3.0.0');


/**
 * Extend the 'install' command.
 */
$cmd = $app->get('install');
$app
    ->command('install', function (InputInterface $input, OutputInterface $output, $withMariadb, $withMysql8, $withBinary) use ($cmd) {
        if ($withMariadb && $withMysql8) {
            throw new Exception('Cannot install Valet+ with both MariaDB and Mysql8, please pick one.');
        }
        $mySqlVersion = $withMariadb ? 'mariadb' : 'mysql@5.7';
        $mySqlVersion = $withMysql8 ? 'mysql' : $mySqlVersion;

        // Add custom options to original command to fake 'm.
        $cmd->addOption('with-mysql-8', null, InputOption::VALUE_NONE)
            ->addOption('with-mariadb', null, InputOption::VALUE_NONE)
            ->addOption('with-binary', 'b', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL);
        // Run original command.
        $cmd->run($input, $output);

        info("\nInstalling Valet+ services");

        Mysql::install($mySqlVersion);
        Mailhog::install(Configuration::read()['tld']);
        Nginx::restart();

        // If 'with-binary' option is omitted, $withBinary is an empty array, we install all.
        // If 'with-binary' option is provided without values, $withBinary is an array with only NULL as value, we install none.
        // Otherwise, we install the provided binaries.
        if (empty($withBinary)) {
            Binary::install();
        } else {
            foreach ($withBinary as $binary) {
                Binary::installBinary($binary);
            }
        }

        info("\nValet+ installed successfully!");
    })
    ->descriptions('Install the Valet services')
    ->addOption('with-mysql-8', null, InputOption::VALUE_NONE, "Install with MySQL 8")
    ->addOption('with-mariadb', null, InputOption::VALUE_NONE, "Install with MariaDB")
    ->addOption(
        'with-binary',
        'b',
        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
        "Install with binary, default no binaries are installed\n" .
        "Supported binaries: " . implode(', ', Binary::getSupported()) . "\n"
    );


/**
 * Most commands are available only if Valet+ is installed.
 */
if (is_dir(VALET_HOME_PATH)) {
    /**
     * Extend the 'tld' command.
     */
    $cmd = $app->get('tld');
    $app->command('tld [tld]', function (InputInterface $input, OutputInterface $output, $tld = null) use ($cmd) {
        $cmd->run($input, $output);

        Mailhog::updateDomain(Configuration::read()['tld']);
        Mailhog::restart();

        PhpFpm::restart();
        Nginx::restart();

        info('Your Valet+ TLD has been updated to [' . $tld . '].');
    }, ['domain'])->descriptions('Get or set the TLD used for Valet sites.');

    /**
     * Extend the 'start' command.
     */
    $cmd = $app->get('start');
    $app->command('start [service]', function (InputInterface $input, OutputInterface $output, $service) use ($cmd) {
        switch ($service) {
            case '':
                Mysql::restart();
                Mailhog::restart();
                break;
            case 'mysql':
                Mysql::restart();

                return info('Mysql has been started.');
            case 'mailhog':
                Mailhog::restart();

                return info('Mailhog has been started.');
        }

        $cmd->run($input, $output);
    })->descriptions('Start the Valet services');

    /**
     * Extend the 'restart' command.
     */
    $cmd = $app->get('restart');
    $app->command('restart [service]', function (InputInterface $input, OutputInterface $output, $service) use ($cmd) {
        switch ($service) {
            case '':
                Mysql::restart();
                Mailhog::restart();
                break;
            case 'mysql':
                Mysql::restart();

                return info('Mysql has been restarted.');
            case 'mailhog':
                Mailhog::restart();

                return info('Mailhog has been restarted.');
        }

        $cmd->run($input, $output);
    })->descriptions('Restart the Valet services');

    /**
     * Extend the 'stop' command.
     */
    $cmd = $app->get('stop');
    $app->command('stop [service]', function (InputInterface $input, OutputInterface $output, $service) use ($cmd) {
        switch ($service) {
            case '':
                Mysql::stop();
                Mailhog::stop();
                break;
            case 'mysql':
                Mysql::stop();

                return info('Mysql has been stopped.');
            case 'mailhog':
                Mailhog::stop();

                return info('Mailhog has been stopped.');
        }

        $cmd->run($input, $output);
    })->descriptions('Stop the Valet services');

    /**
     * Extend the 'uninstall' command.
     */
    $cmd = $app->get('uninstall');
    $app->command('uninstall [--force]', function (InputInterface $input, OutputInterface $output, $force) use ($cmd) {
        if ($force) {
            warning('YOU ARE ABOUT TO UNINSTALL Valet+ services, configs and logs.');
            $helper   = $this->getHelperSet()->get('question');
            $question = new ConfirmationQuestion('Are you sure you want to proceed? [y/N]', false);

            if (false === $helper->ask($input, $output, $question)) {
                return warning('Uninstall aborted.');
            }

            info('Removing mysql...');
            Mysql::uninstall();
            info('Removing mailhog...');
            Mailhog::uninstall();
            info('Removing binaries...');
            Binary::uninstall();
            info('Removing varnish...');
            Varnish::uninstall();
            info('Removing rabbitmq...');
            Rabbitmq::uninstall();
            info('Removing elasticsearch...');
            Elasticsearch::uninstall();
        }

        $cmd->run($input, $output);
    })->descriptions('Uninstall the Valet services', ['--force' => 'Do a forceful uninstall of Valet and related Homebrew pkgs']);

    /**
     * @todo: Extend the 'log' command to log 'elasticsearch', 'mysql'.
     */


    /**
     * Mailhog services.
     */
    $app
        ->command('mailhog', function (OutputInterface $output, string $mode = null) {
            $modes = ['install', 'on', 'enable', 'off', 'disable'];

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
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
        })
        ->descriptions('Enable/disable Mailhog')
        ->addArgument('mode', InputArgument::REQUIRED, 'Available modes: ' . implode(', ', ['install', 'on', 'enable', 'off', 'disable']));

    /**
     * Varnish services.
     */
    $app
        ->command('varnish', function (OutputInterface $output, string $mode = null) {
            $modes = ['install', 'on', 'enable', 'off', 'disable'];

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
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
        })
        ->descriptions('Enable/disable Varnish')
        ->addArgument('mode', InputArgument::REQUIRED, 'Available modes: ' . implode(', ', ['install', 'on', 'enable', 'off', 'disable']));

    /**
     * Rabbitmq services.
     */
    $app
        ->command('rabbitmq', function (OutputInterface $output, string $mode = null) {
            $modes = ['install', 'on', 'enable', 'off', 'disable'];

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
            }

            switch ($mode) {
                case 'install':
                    Rabbitmq::install();

                    return;
                case 'enable':
                case 'on':
                    Rabbitmq::enable();

                    return;
                case 'disable':
                case 'off':
                    Rabbitmq::disable();

                    return;
            }
        })
        ->descriptions('Enable/disable Rabbitmq')
        ->addArgument('mode', InputArgument::REQUIRED, 'Available modes: ' . implode(', ', ['install', 'on', 'enable', 'off', 'disable']));

    /**
     * Database services and commands.
     */
    $app
        ->command('db [run] [name] [optional] [-y|--yes]', function ($input, $output, $run, $name, $optional) {
            $helper   = $this->getHelperSet()->get('question');
            $defaults = $input->getOptions();

            if ($run === 'list' || $run === 'ls') {
                Mysql::listDatabases();

                return;
            }

            if ($run === 'create') {
                $databaseName = Mysql::createDatabase($name);
                if (!$databaseName) {
                    warning('Error creating database');

                    return;
                }

                info('Database "' . $databaseName . '" created successfully');

                return;
            }

            if ($run === 'drop') {
                if (!$defaults['yes']) {
                    $question = new ConfirmationQuestion('Are you sure you want to delete the database? [y/N] ', false);
                    if (!$helper->ask($input, $output, $question)) {
                        return;
                    }
                }

                $databaseName = Mysql::dropDatabase($name);
                if (!$databaseName) {
                    warning('Error dropping database');

                    return;
                }

                info('Database "' . $databaseName . '" dropped successfully');

                return;
            }

            if ($run === 'reset') {
                if (!$defaults['yes']) {
                    $question = new ConfirmationQuestion('Are you sure you want to reset the database? [y/N] ', false);
                    if (!$helper->ask($input, $output, $question)) {
                        return;
                    }
                }

                $dropped = Mysql::dropDatabase($name);
                if (!$dropped) {
                    warning('Error deleting database');

                    return;
                }

                $databaseName = Mysql::createDatabase($name);
                if (!$databaseName) {
                    warning('Error creating database');

                    return;
                }

                info('Database "' . $databaseName . '" reset successfully');

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
        })
        ->descriptions('Database commands (list/ls, create, drop, reset, import, reimport, export/dump, pwd/password)');

    $app
        ->command('elasticsearch', function (InputInterface $input, OutputInterface $output, $mode, $targetVersion = null) {
            $modes         = ['install', 'on', 'enable', 'off', 'disable', 'use'];
            $targetVersion = $targetVersion ?? 'opensearch';

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
            }

            switch ($mode) {
                case 'on':
                case 'install':
                case 'use':
                    Elasticsearch::useVersion($targetVersion);
                    break;
                case 'off':
                case 'uninstall':
                    Elasticsearch::uninstall();
                    break;
            }

        })
        ->descriptions('Enable/disable/switch Elasticsearch')
        ->setAliases(['es'])
        ->addArgument('mode', InputArgument::REQUIRED, 'Available modes: ' . implode(', ', ['install', 'on', 'enable', 'off', 'disable', 'use']))
        ->addArgument('targetVersion', InputArgument::OPTIONAL, "Version to use", null);


    /**
     * Xdebug php extension.
     */
    $app->command('xdebug [mode]', function ($mode) {
        $modes = ['on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
        }

        $restart = false;
        switch ($mode) {
            case 'on':
            case 'install':
                $restart = Xdebug::install();
                break;
            case 'off':
            case 'uninstall':
                $restart = Xdebug::uninstall();
                break;
        }
        if ($restart) {
            PhpFpm::restart();
        }
    })->descriptions('Enable/disable Xdebug');

    /**
     * Memcache php extension.
     */
    $app->command('memcache [mode]', function ($mode) {
        $modes = ['on', 'enable', 'off', 'disable'];

        if (!in_array($mode, $modes)) {
            throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
        }

        $restart = false;
        switch ($mode) {
            case 'on':
            case 'install':
                $restart = Memcache::install();
                break;
            case 'off':
            case 'uninstall':
                $restart = Memcache::uninstall();
                break;
        }
        if ($restart) {
            PhpFpm::restart();
        }
    })->descriptions('Enable/disable Memcache');


    /**
     * Rewrite commands.
     */
    $app->command('rewrite [url]', function ($url = null) {
        if (!$url) {
            warning('Aborting, url is required');

            return;
        }

        $host = Site::host(getcwd());
        $url  = Site::rewrite($url, $host);
        if ($url === false) {
            warning('Aborting, url rewrite failed, might already exist');

            return;
        }

        info("The [$url] will now rewrite traffic to [$host].");
    })->descriptions('Rewrite any public URL to your local site instance.');

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
}

/**
 * Run the application.
 */
$app->run();
