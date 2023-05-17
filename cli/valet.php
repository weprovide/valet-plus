#!/usr/bin/env php
<?php

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Valet\info;


// Require Laravel's Valet.
require_once __DIR__ . '/../vendor/laravel/valet/cli' . '/app.php';
require_once __DIR__ . '/includes/extends.php';


// Change name and version.
$app->setName('Valet+');
$app->setVersion('3.0.0');


/**
 * Extend the install command.
 */
$cmd = $app->get('install');
$app
    ->command('install', function (InputInterface $input, OutputInterface $output) use ($cmd) {
        $cmd->run($input, $output);

        info("\nInstalling Valet+ services");

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
}

/**
 * Run the application.
 */
$app->run();
