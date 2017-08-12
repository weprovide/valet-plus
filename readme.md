<p align="center"><img width="200" src="images/logo.png"></p>

## Introduction

Squire is a fork of the [Laravel Valet](https://github.com/laravel/valet) project, designed to add a feature set that wasn't supported in the original project.

Squire, like Valet, is a development environment for macOS. Both projects configure your Mac to always run Nginx in the background when your machine starts. Then, using [DnsMasq](https://en.wikipedia.org/wiki/Dnsmasq), they proxy all requests on the `*.dev` domain to point to sites installed on your local machine.

Some key differences compared to Valet:

- PHP version switch
- Xdebug (on/off mode)
- PHP extensions (mcrypt, intl, opcache, apcu)
- Optimized PHP configuration using opcache
- Mysql (with optimized configuration)
- Redis
- Elasticsearch (optional)
- Many more features outlined below...

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
- [Squire drivers](#squire-drivers)
- [Squire Documentation](#squire-documentation)
- [Credits](#credits)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Installation

> :warning: Squire requires macOS and [Homebrew](https://brew.sh/). Before installation, you should make sure that no other programs such as Apache or Nginx are binding to your local machine's port 80.

1. Install or update [Homebrew](https://brew.sh/) to the latest version using brew update.
2. Install PHP 7.0 using Homebrew via `brew install homebrew/php/php70`.
3. Install Composer using Homebrew via `brew install homebrew/php/composer`.
4. Install Squire with Composer via `composer global require weprovide/squire`.
5. Add `export PATH="$PATH:$HOME/.composer/vendor/bin"` to `.bash_profile` (for bash) or `.zshrc` (for zsh) depending on your shell (`echo $SHELL`)
6. Run the `squire install` command. Optionally add `--with-mariadb` to use MariaDB instead of MySQL This will configure and install Squire and DnsMasq, and register Squire's daemon to launch when your system starts.
7. Once Squire is installed, try pinging any *.dev domain on your terminal using a command such as `ping foobar.dev`. If Squire is installed correctly you should see this domain responding on `127.0.0.1`. If not you might have to restart your system. Especially when coming from the Dinghy (docker) solution.

> :information_source: Squire will automatically start its daemon each time your machine boots. There is no need to run `squire start` or `squire install` ever again once the initial Squire installation is complete.

> :information_source: To update Squire to the latest version use the `composer global require weprovide/squire` command in your terminal. After upgrading, it is good practice to run the `squire install` command so Squire can make additional upgrades to your configuration files if necessary.

### Serving sites

Once Squire is installed, you're ready to start serving sites. Squire provides a command to help you serve your sites: `squire park`. Which will register the current working directory as projects root. Generally this directory is `~/sites`.

1. Create a `sites` directory: `mkdir ~/sites`
2. `cd ~/sites`
3. `squire park`

That's all there is to it. Now, any project you create within your "parked" directory will automatically be served using the http://folder-name.dev convention.

For example:

1. `mkdir ~/sites/example`
2. `cd ~/sites/example`
3. `echo "<?php echo 'Squire at your service' > index.php"`
4. Go to `http://example.dev`, you should see `Squire at your service`

## Switching PHP version

Switch PHP version using one of three commands:

```
squire use 5.6
```

```
squire use 7.0
```

```
squire use 7.1
```

## Xdebug

Xdebug support is build in. It works on port `9000` after you enable it.

Enable Xdebug:

```
squire xdebug on
```

Disable Xdebug:

```
squire xdebug off
```

> :warning: Xdebug makes your environment slower. That's why we allow to fully enable / disable it. When not debugging it's best to disable it by running `squire xdebug off`.

### PHPstorm

To use Xdebug with PHPstorm you don't have to configure anything. Just run `squire xdebug on` and click the Xdebug button on the top right:

![xdebug-phpstorm](images/xdebug-phpstorm.png)

Then install [Xdebug helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc) for Chrome and enable it when viewing the page you want to use Xdebug on.

## Ioncube

Enable Ioncube:

```
squire ioncube on
```

Disable Ioncube:

```
squire ioncube off
```

## Database
Squire automatically installs mysql 5.7 with 5.6 compatibility mode included. It includes a tweaked my.cnf which is aimed at improving speed.

Username: `root`

Password: `root`

## List databases

```
squire db ls
```

### Creating databases

Create databases using:

```
squire db create <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

```
squire db create
```

### Dropping databases

Drop a database using:

```
squire db drop <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

```
squire db drop
```

### Resetting databases

Drop and create a database using:

```
squire db reset <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

```
squire db reset
```

### Exporting databases

Export a database:

```
squire db export <filename> <database>
```

When no database is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

When no filename is given it will use `<database>-<date>.sql`. Optionally you can use `squire db export - <database>` to automatically generate the filename for a custom database.

### Importing databases

Import a database with progress bar

```
squire db import <filename>.sql <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll use the current working directory name.

### Open database in Sequel Pro

Squire has first class support for opening databases in [Sequel Pro](https://www.sequelpro.com/), a popular MySQL client for Mac.

```
squire db open <name>
```

When no name is given it'll try to find the closest git repository directory name. When it can't find one it'll open Sequel Pro without pre-selected database.

```
squire db open
```

## Subdomains

You can manage subdomains for the current working directory using:

```
squire subdomain list
```

```
squire subdomain add <subdomain>
```

For example:

```
squire subdomain add welcome
```

Will create `welcome.yourproject.dev`.

## Mailhog

Mailhog is used to catch emails send from php. You can access the panel at [http://mailhog.dev](http://mailhog.dev).

## Redis

Redis is automatically installed and listens on the default port `6379`. The redis socket is located at `/tmp/redis.sock`

## Elasticsearch

Elasticsearch can be installed using:

```
squire elasticsearch install
```

It will run on the default port `9200`, and is accessible at [http://elasticsearch.dev/](http://elasticsearch.dev/).

## Framework specific development tools

Squire will automatically install framework specific development tools for you:

- [wp-cli](http://wp-cli.org/) available as `wp`
- [n98-magerun](https://github.com/netz98/n98-magerun) available as `magerun`
- [n98-magerun2](https://github.com/netz98/n98-magerun2) available as `magerun2` for you.

## Git Tower

Open current git project in [Tower](https://www.git-tower.com/mac/)

```
squire tower
```

## PHPstorm

Open current git project in [PHPstorm](https://www.jetbrains.com/phpstorm/)

```
squire phpstorm
```

## VScode

Open current git project in [Visual Studio Code](https://code.visualstudio.com/)

```
squire vscode
```

## Open project in browser

To open the current project in your default browser:

```
squire open
```

## Copy ssh key

```
squire ssh-key
```

## Automatic configuration [beta]

Automatically configure environment for the project you're in.

```
squire configure
```

### Supported systems

#### Magento 2

Automatically configure the `env.php`, `config.php` base url and elastic search configuration in the database for Magento 2.

#### Magento 1

Automatically configure the `local.xml` and base url in the database for Magento 1.


## Securing Sites With TLS

By default, Squire serves sites over plain HTTP. However, if you would like to serve a site over encrypted TLS using HTTP/2, use the secure command. For example, if your site is being served by Squire on the example.dev domain, you should run the following command to secure it:

```
squire secure example
```

To "unsecure" a site and revert back to serving its traffic over plain HTTP, use the unsecure command. Like the secure command, this command accepts the host name that you wish to unsecure:

```
squire unsecure example
```

## Log locations

The `nginx-error.log` and `mysql.log` are located at `~/.squire/Log`.

Other logs, including the PHP error log, are located at `/usr/local/var/log`

## Squire drivers
Squire uses Valet drivers to handle requests. You can read more about those [here](https://laravel.com/docs/5.4/valet#custom-valet-drivers).

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

A full list can be found [here](cli/drivers).

## Custom Squire Drivers

You can write your own Squire "driver" to serve PHP applications running on another framework or CMS that is not natively supported by Squire. When you install Squire, a `~/.squire/Drivers` directory is created which contains a `SampleSquireDriver.php` file. This file contains a sample driver implementation to demonstrate how to write a custom driver. Writing a driver only requires you to implement three methods: `serves`, `isStaticFile`, and `frontControllerPath`.

All three methods receive the `$sitePath`, `$siteName`, and `$uri` values as their arguments. The `$sitePath` is the fully qualified path to the site being served on your machine, such as `/Users/Lisa/Sites/my-project`. The `$siteName` is the "host" / "site name" portion of the domain (`my-project`). The `$uri` is the incoming request URI (`/foo/bar`).

Once you have completed your custom Squire driver, place it in the `~/.squire/Drivers` directory using the `FrameworkSquireDriver.php` naming convention. For example, if you are writing a custom squire driver for WordPress, your file name should be `WordPressSquireDriver.php`.

Let's take a look at a sample implementation of each method your custom Squire driver should implement.

#### The `serves` Method

The `serves` method should return `true` if your driver should handle the incoming request. Otherwise, the method should return `false`. So, within this method you should attempt to determine if the given `$sitePath` contains a project of the type you are trying to serve.

For example, let's pretend we are writing a `WordPressSquireDriver`. Our serve method might look something like this:

```
/**
 * Determine if the driver serves the request.
 *
 * @param  string  $sitePath
 * @param  string  $siteName
 * @param  string  $uri
 * @return bool
 */
public function serves($sitePath, $siteName, $uri)
{
    return is_dir($sitePath.'/wp-admin');
}

```

#### The `isStaticFile` Method

The `isStaticFile` should determine if the incoming request is for a file that is "static", such as an image or a stylesheet. If the file is static, the method should return the fully qualified path to the static file on disk. If the incoming request is not for a static file, the method should return `false`:

```
/**
 * Determine if the incoming request is for a static file.
 *
 * @param  string  $sitePath
 * @param  string  $siteName
 * @param  string  $uri
 * @return string|false
 */
public function isStaticFile($sitePath, $siteName, $uri)
{
    if (file_exists($staticFilePath = $sitePath.'/public/'.$uri)) {
        return $staticFilePath;
    }

    return false;
}

```

> {note} The `isStaticFile` method will only be called if the `serves` method returns `true` for the incoming request and the request URI is not `/`.

#### The `frontControllerPath` Method

The `frontControllerPath` method should return the fully qualified path to your application's "front controller", which is typically your "index.php" file or equivalent:

```
/**
 * Get the fully resolved path to the application's front controller.
 *
 * @param  string  $sitePath
 * @param  string  $siteName
 * @param  string  $uri
 * @return string
 */
public function frontControllerPath($sitePath, $siteName, $uri)
{
    return $sitePath.'/public/index.php';
}

```

### Local Drivers

If you would like to define a custom Squire driver for a single application, create a `LocalSquireDriver.php` in the application's root directory. Your custom driver may extend the base `SquireDriver` class or extend an existing application specific driver such as the `LaravelSquireDriver`:

```
class LocalSquireDriver extends LaravelSquireDriver
{
    /**
     * Determine if the driver serves the request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return bool
     */
    public function serves($sitePath, $siteName, $uri)
    {
        return true;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {
        return $sitePath.'/public_html/index.php';
    }
}
```

## Valet Documentation

Documentation for Valet can be found on the [Laravel website](https://laravel.com/docs/valet).

## Credits

This project is a fork of [laravel/valet](https://github.com/laravel/valet). Thanks to everyone who contributed to this project.

## Authors

- Tim Neutkens ([@timneutkens](https://github.com/timneutkens))
- (Valet) Taylor Otwell ([@taylorotwell](https://github.com/taylorotwell))
- (Valet) Adam Wathan ([@adamwathan](https://github.com/adamwathan))
