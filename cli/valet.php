#!/usr/bin/env php
<?php

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function Valet\info;
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
$app->command('install', function (InputInterface $input, OutputInterface $output) use ($cmd) {
    $cmd->run($input, $output);

    info("\nInstalling Valet+ services");

    $mySqlVersion = $this->getHelperSet()->get('question')->ask(
        $input,
        $output,
        new ChoiceQuestion('Which database would you like to install?', Mysql::getSupportedVersions())
    );

    Mysql::install($mySqlVersion);
    Mailhog::install(Configuration::read()['tld']);
    Nginx::restart();

    info("\nValet+ installed successfully!");
})->descriptions('Install the Valet services');


/**
 * Most commands are available only if valet+ is installed.
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
     * Redis services.
     */
    $app
        ->command('redis', function (OutputInterface $output, string $mode = null) {
            $modes = ['install', 'on', 'enable', 'off', 'disable'];

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
            }

            switch ($mode) {
                case 'install':
                    Redis::install();

                    return;
                case 'enable':
                case 'on':
                    Redis::enable();

                    return;
                case 'disable':
                case 'off':
                    Redis::disable();

                    return;
            }
        })
        ->descriptions('Enable/disable Redis')
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

    /**
     * Xdebug
     */
    $app
        ->command('xdebug', function (InputInterface $input, OutputInterface $output, string $mode = null) {
            $modes = ['on', 'enable', 'off', 'disable'];

            if (!in_array($mode, $modes)) {
                throw new Exception('Mode not found. Available modes: ' . implode(', ', $modes));
            }

            $restart    = false;
            $phpVersion = PhpFpm::parsePhpVersion(Brew::linkedPhp());
            $phpIniPath = PhpFpm::fpmConfigPath($phpVersion);
            $options    = $input->getOptions();
            if (isset($options['remote_autostart'])) {
//                if ($options['remote_autostart']) {
//                    PhpFpm::enableAutoStart();
//                } else {
//                    PhpFpm::disableAutoStart();
//                }
//                $restart = true;
            }

            if ($mode === 'on' || $mode === 'enable') {
                if (!PhpExtension::isInstalled('xdebug', $phpVersion)) {
                    PhpExtension::installExtension('xdebug', $phpVersion);
                    PhpExtension::installXdebugConfiguration($phpIniPath);
                    $restart = true;
                } else {
                    info("Xdebug extension is already enabled!");
                }
            }
            if ($mode === 'off' || $mode === 'disable') {
                if (PhpExtension::isInstalled('xdebug', $phpVersion)) {
                    PhpExtension::uninstallXdebugConfiguration($phpIniPath);
                    PhpExtension::uninstallExtension('xdebug', $phpVersion, $phpIniPath);
                    $restart = true;
                } else {
                    info("Xdebug extension is already disabled!");
                }
            }

            if ($restart) {
                PhpFpm::restart();
            }
        })
        ->descriptions('Enable/disable Xdebug')
        ->addArgument('mode', InputArgument::REQUIRED, 'Available modes: ' . implode(', ', ['on', 'enable', 'off', 'disable']));
//        ->addOption('remote_autostart', 'r', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL);
}

/**
 * Run the application.
 */
$app->run();
