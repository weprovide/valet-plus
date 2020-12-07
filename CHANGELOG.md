# Changelog
All notable changes to valet-plus will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/weprovide/valet-plus/compare/2.1.0...2.x)
- [#546] Add support for PHP 8.0

## [2.1.0](https://github.com/weprovide/valet-plus/compare/2.0.0...2.1.0)
## Removed
- [#524] Removed support for Elasticsearch 2.4
- [#524] Removed support for Elasticsearch 5.6
- [#524] Removed support for Elasticsearch 7.6
- [#516] Removed setting the query parameter for Drupal.
- [#535] Removed old PHP fixes within valet fix for old brew formulae now replaced by henkrehost/php

## Added
- [#524] Added support for Elasticsearch 7.8
- [#502] Added to PHP xdebug config xdebug.max_nesting_level to -1
- [#530] Added Composer.lock file
- [#487] Added brew update and uninstall brew php pipeline step
- [#470] Added macOS catalina image for testing on macos catalina

## Changed
- [#501] Changed PHP memory limit to 4 GB
- [#454] Changed the valet unlink command to improve feedback and help description.

## Fixed
- [#541] Fixed PECL YML package no longer supporting PHP 7.0 beyond version 2.0.4.
- [#539] Fixed the CI/CD not failing when switch to (php) step fails 
- [#537] Fixed valet fix forcefully trying to reinstall and relink the default PHP version. 
Even when not on the default version. Causing a faulty link process (overwriting).
- [#533] Fixed CI/CD not adhering to installation instructions
- [#528] Fixed syntax error due to symfony/polyfill-php80 being required by new version (4.4.9) of symfony/debug
- [#530] Fixed composer.lock being ignored causing composer updates to run during install
- [#464] Fixed logic to copy Elasticsearch NGINX stub on install
- [#455] Fixed bug where switching PHP version to a flawed installation would result in an unusable valet-plus installation.

## [2.0.0](https://github.com/weprovide/valet-plus/compare/1.0.29...2.0.0)

## Removed
- [#393] Major part of the readme, now available in the [WIKI](https://github.com/weprovide/valet-plus/wiki)
- [#437] Dependency on mysql-utilities because brew has deprecated the use of the formula.

### Fixed 
- [#401] Installation bug with apcu_bc due to double .so directives getting placed in php.ini.
- [#402] Installation bug with ioncube due to PHP 7.3 not being configured.
- [#403] Installation bug with memcached on MacOS Mojave due to zlib not being installed by default anymore.
- [#408] Installation bug with Elasticsearch due to PECL yaml not being installed.
- [#408] Bug while switching Elasticsearch version by always suffixing the version in the datapath config.
- [#425] Bug in custom PECL extensions throwing errors when no url is set for custom extension.
- [#426] Bug in valet fix command trying to install PHP 7.1 as default.
- [#435] Bug in fix command as all errors needed to be present to fire fix logic.
- [#449] Xdebug not installing on PHP version 7.0 due to new xdebug version which only supports PHP 7.1+.
- [#447] Versioned MySQL (E.G: MySQL@5.7) not being linked upon install.

### Changed
- [#399] Existing files to adhere to PSR-2 code style.
- [#414] Magerun to version 1.103.1.
- [#414] Magerun2 to version 3.2.0.

### Added
- [#393] Changelog file to keep track of changes.
- [#394] Azure pipelines integration as start for the automated testing setup.
- [#399] PHP_CodeSniffer to project dependencies to ensure code style validation tools.
- [#404] Github issue templates to enforce workflows.
- [#404] Contributor guidelines `CONTRIBUTING.md` to explain workflows.
- [#425] Added support for switching to PHP 7.4.

[#393]: https://github.com/weprovide/valet-plus/issues/393

[#394]: https://github.com/weprovide/valet-plus/pull/394
[#399]: https://github.com/weprovide/valet-plus/pull/399
[#401]: https://github.com/weprovide/valet-plus/pull/401
[#402]: https://github.com/weprovide/valet-plus/pull/402
[#403]: https://github.com/weprovide/valet-plus/pull/403
[#408]: https://github.com/weprovide/valet-plus/pull/408
[#404]: https://github.com/weprovide/valet-plus/pull/404
[#414]: https://github.com/weprovide/valet-plus/pull/414
[#425]: https://github.com/weprovide/valet-plus/pull/425
[#426]: https://github.com/weprovide/valet-plus/pull/426
[#435]: https://github.com/weprovide/valet-plus/pull/435
[#437]: https://github.com/weprovide/valet-plus/pull/437
[#447]: https://github.com/weprovide/valet-plus/pull/447
[#454]: https://github.com/weprovide/valet-plus/pull/454
[#455]: https://github.com/weprovide/valet-plus/pull/455
[#470]: https://github.com/weprovide/valet-plus/pull/470
[#487]: https://github.com/weprovide/valet-plus/pull/487
[#449]: https://github.com/weprovide/valet-plus/pull/449
[#464]: https://github.com/weprovide/valet-plus/pull/464
[#502]: https://github.com/weprovide/valet-plus/pull/502
[#501]: https://github.com/weprovide/valet-plus/pull/501
[#516]: https://github.com/weprovide/valet-plus/pull/516
[#524]: https://github.com/weprovide/valet-plus/pull/524
[#528]: https://github.com/weprovide/valet-plus/pull/528
[#530]: https://github.com/weprovide/valet-plus/pull/530
[#533]: https://github.com/weprovide/valet-plus/pull/533
[#535]: https://github.com/weprovide/valet-plus/pull/535
[#537]: https://github.com/weprovide/valet-plus/pull/537
[#539]: https://github.com/weprovide/valet-plus/pull/539
[#541]: https://github.com/weprovide/valet-plus/pull/541
