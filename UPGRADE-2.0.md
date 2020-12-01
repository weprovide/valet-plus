UPGRADE FROM 1.0.x to 2.0
=======================

### APCU_BC install

  * Due to issues with the `apcu_bc` pecl extension install and [growing support for PHP 7.0+](https://pecl.php.net/package/APCu)
  within the `apcu` pecl extension, deprecate the use of `apcu_bc`. To ensure new `apcu` install
  do the following for every PHP version installed:

      * Uninstall your pecl installed apcu extensions `pecl uninstall apcu && pecl uninstall apcu_bc`

      * Remove the `apcu.so` and `apc.so` extensions from `/usr/local/etc/valet-php/<version>/php.ini`
