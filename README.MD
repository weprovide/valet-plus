<p align="center"><img width="200" src="images/logo.png"></p>

## Introduction

Valet+ is a development environment for macOS. No Vagrant, no Docker, no `/etc/hosts` file.


## Installation

This project uses [laravel/valet](https://github.com/laravel/valet) as a dependency. You can follow the installation 
instructions of Laravel's Valet.

> Note: install php@8.0 first before installing Valet+.


## Valet+ features

Here are a few key differences compared to the original Valet:

- Nginx config optimization
- PHP extensions (~~mcrypt~~, ~~intl~~, ~~opcache~~, yaml, apcu)
- MySQL (mysql@5.7, mysql@8, mariadb)
  - DB commands (list, create, drop, reset, (re)import, export, ~~open~~)
- Mailhog (on/off mode)
- ~~Redis (on/off mode)~~
- Rabbitmq (on/off mode)
- Varnish (on/off mode)
- Xdebug (on/off mode)
- Memcache (on/off mode)
- Elasticsearch v6, ~~v7~~, ~~v8~~ (on/off mode) 
- Opensearch (on/off mode)
- ~~Ioncube~~
- Rewrite/unrewrite public domain to local environment
- ~~DevTools~~
- Binaries (magerun, magerun2, drush)

### Changes vs Valet+ 2

- Rename `.env.valet` to `.valet-env.php`.
- Use command `valet elasticsearch|es use <version>` instead of `valet use elasticsearch|es <version>`.
- Choose which binaries to install (default all) and self-update on `valet install` command.



## Valet+ Authors

- Tim Neutkens ([@timneutkens](https://github.com/timneutkens))
- Sam Granger ([@samgranger](https://github.com/samgranger))
- Mischa Braam ([@mischabraam](https://github.com/mischabraam))

## Valet+ Contributors

- Lou van der Laarse ([@Neodork](https://github.com/Neodork))
- Sander Pott (@Sander Valet+ slack channel)



## Get in touch

[![slack](https://p9.zdassets.com/hc/theme_assets/138842/200037786/logo.png)](https://join.slack.com/t/valet-plus/shared_invite/enQtNDE2MjU2NzgyNjQwLWFiYWNjOWFhOWQ2ZDcyOTEyZTA2MzAzOWYyYzYwMTYzODVlMGE3ZDg3ZWQ1M2JmN2M0OGY3OGUwMDI3NDM1NDU)

We have a slack workspace available [which you can join](https://join.slack.com/t/valet-plus/shared_invite/enQtNDE2MjU2NzgyNjQwLWFiYWNjOWFhOWQ2ZDcyOTEyZTA2MzAzOWYyYzYwMTYzODVlMGE3ZDg3ZWQ1M2JmN2M0OGY3OGUwMDI3NDM1NDU).

Yes, we have a Valet+ fan page https://www.weprovide.com/valet-plus
