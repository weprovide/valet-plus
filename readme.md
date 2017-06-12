<p align="center"><img src="https://laravel.com/assets/img/components/logo-valet.svg"></p>

## Introduction

Valet+ is a development environment for macOS. No Vagrant, no Docker, no `/etc/hosts` file. _Yeah, we like it too._

Valet+ configures your Mac to always run Nginx in the background when your machine starts. Then, using [DnsMasq](https://en.wikipedia.org/wiki/Dnsmasq), Valet proxies all requests on the `*.dev` domain to point to sites installed on your local machine.

In other words, a blazing fast development environment. Valet provides a great alternative if you want flexible basics or prefer extreme speed.

## Table of contents

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
- [Introduction](#introduction)
- [Installation](#installation)
  - [Serving sites](#serving-sites)
- [Switching PHP version](#switching-php-version)
- [Xdebug](#xdebug)
  - [PHPstorm](#phpstorm)
- [Database](#database)
  - [Creating databases](#creating-databases)
  - [Importing databases](#importing-databases)
  - [Open database in Sequel Pro](#open-database-in-sequel-pro)
- [Redis](#redis)
- [Open project in browser](#open-project-in-browser)
- [Securing Sites With TLS](#securing-sites-with-tls)
- [Valet drivers](#valet-drivers)
- [Valet Documentation](#valet-documentation)
- [Credits](#credits)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Installation

> :warning: Valet requires macOS and [Homebrew](https://brew.sh/). Before installation, you should make sure that no other programs such as Apache or Nginx are binding to your local machine's port 80.

1. Install or update [Homebrew](https://brew.sh/) to the latest version using brew update.
2. Install PHP 7.1 using Homebrew via `brew install homebrew/php/php71`.
3. Install Valet+ with Composer via `composer global require timneutkens/valet-plus`. Make sure the  `~/.composer/vendor/bin` directory is in your system's "PATH".
4. Run the `valet install` command. This will configure and install Valet+ and DnsMasq, and register Valet's daemon to launch when your system starts.
5. Once Valet is installed, try pinging any *.dev domain on your terminal using a command such as `ping foobar.dev`. If Valet+ is installed correctly you should see this domain responding on `127.0.0.1`. If not you might have to restart your system. Especially when coming from the Dinghy (docker) solution.

> :information_source: Valet will automatically start its daemon each time your machine boots. There is no need to run `valet start` or `valet install` ever again once the initial Valet+ installation is complete.

> :information_source: To update Valet+ to the latest version use the `composer global update` command in your terminal. After upgrading, it is good practice to run the `valet install` command so Valet can make additional upgrades to your configuration files if necessary.

### Serving sites

Once Valet+ is installed, you're ready to start serving sites. Valet provides a command to help you serve your sites: `valet park`. Which will register the current working directory as projects root. Generally this directory is `~/sites`.

1. Create a `sites` directory: `mkdir ~/sites`
2. `cd ~/sites`
3. `valet park`

That's all there is to it. Now, any project you create within your "parked" directory will automatically be served using the http://folder-name.dev convention.

For example:

1. `mkdir ~/sites/example`
2. `cd ~/sites/example`
3. `echo "<?php echo 'Valet+ at your service' > index.php"`
4. Go to `http://example.dev`, you should see `Valet+ at your service`

## Switching PHP version

Switch PHP version using one of there commands:

```
valet use 5.6
```

```
valet use 7.0
```

```
valet use 7.1
```

## Xdebug

Xdebug support is build in. It works on port `9000` after you enable it.

Enable Xdebug:

```
valet xdebug on
```

Disable Xdebug:

```
valet xdebug off
```

> :warning: Xdebug makes your environment slower. That's why we allow to fully enable / disable it. When not debugging it's best to disable it by running `valet xdebug off`.

### PHPstorm

To use Xdebug with PHPstorm you don't have to configure anything. Just run `valet xdebug on` and click the Xdebug button on the top right:

![xdebug-phpstorm](images/xdebug-phpstorm.png)

Then install [Xdebug helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc) for Chrome and enable it when viewing the page you want to use Xdebug on.


## Database
Valet+ automatically installs mysql 5.7 with 5.6 compatibility mode included. It includes a tweaked my.cnf which is aimed at improving speed.

### Creating databases

Create databases using:

```
valet db create <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

```
valet db create
```

### Importing databases

Import a database with progress bar

```
valet db import <filename>.sql
```

### Open database in Sequel Pro

Valet+ has first class support for opening databases in [Sequel Pro](https://www.sequelpro.com/), a popular MySQL client for Mac.

```
valet db open <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll open Sequel Pro without pre-selected database.

```
valet db open
```

## Mailhog

Mailhog is used to catch emails send from php. You can access the panel at [http://localhost:8025](http://localhost:8025).

## Redis

Redis is automatically installed and listens on the default port `6379`. The redis socket is located at `/tmp/redis.sock`

## Git Tower

Open current git project in [Tower](https://www.git-tower.com/mac/)

```
valet tower
```

## PHPstorm

Open current git project in [PHPstorm](https://www.jetbrains.com/phpstorm/)

```
valet phpstorm
```

## VScode

Open current git project in [Visual Studio Code](https://code.visualstudio.com/)

```
valet vscode
```

## Open project in browser

To open the current project in your default browser:

```
valet open
```

## Copy ssh key

```
valet ssh-key
```

## Securing Sites With TLS

By default, Valet serves sites over plain HTTP. However, if you would like to serve a site over encrypted TLS using HTTP/2, use the secure command. For example, if your site is being served by Valet on the example.dev domain, you should run the following command to secure it:

```
valet secure example
```

To "unsecure" a site and revert back to serving its traffic over plain HTTP, use the unsecure command. Like the secure command, this command accepts the host name that you wish to unsecure:

```
valet unsecure example
```

## Valet drivers
Valet uses drivers to handle requests. You can read more about those [here](https://laravel.com/docs/5.4/valet#custom-valet-drivers).

By default these are included:

- Static HTML
- Magento
- Magento 2
- Symfony
- Wordpress / Bedrock
- Laravel
- Lumen
- CakePHP 3
- Craft
- Jigsaw
- Slim
- Statamic
- Zend Framework

A full list can be found [here](cli/drivers)

## Valet Documentation

Documentation for Valet can be found on the [Laravel website](https://laravel.com/docs/valet).

## Credits

This project is an improved fork of [laravel/valet](https://github.com/laravel/valet). Thanks to everyone who contributed to this project.
