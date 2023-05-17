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
$app
    ->command('install', function (InputInterface $input, OutputInterface $output) use ($cmd) {
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
    })
    ->descriptions('Install the Valet services');


/**
 * Most commands are available only if valet+ is installed.
 */
if (is_dir(VALET_HOME_PATH)) {
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
     * Database commands.
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
}

/**
 * Run the application.
 */
$app->run();
