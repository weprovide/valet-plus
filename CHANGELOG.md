# Changelog
All notable changes to valet-plus will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/weprovide/valet-plus/compare/2.0.0...2.x)
Nothing yet...

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
[#449]: https://github.com/weprovide/valet-plus/pull/449
