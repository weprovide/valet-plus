<?php 

$result = "

Build complete.
Don't forget to run 'make test'.

3367994  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4
3368330  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr
3368331  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local
3368332  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar
3368333  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar/php@7.0
3368334  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar/php@7.0/7.0.29_1
3368335  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar/php@7.0/7.0.29_1/pecl
3368336  0 drwxr-xr-x  3 admin  wheel    102 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar/php@7.0/7.0.29_1/pecl/20151012
3368337 40 -rwxr-xr-x  1 admin  wheel  17212 13 Apr 14:45 /private/tmp/pear/temp/pear-build-adminnbfJaP/install-apcu_bc-1.0.4/usr/local/Cellar/php@7.0/7.0.29_1/pecl/20151012/apc.so

Build process completed successfully
Installing '/usr/local/Cellar/php@7.0/7.0.29_1/pecl/20151012/apc.so'
install ok: channel://pecl.php.net/apcu_bc-1.0.4
Extension apc enabled in php.ini

";

$extension = 'apc';

if (!preg_match("/Installing '(.*$extension\.so)'/", $result, $matches)) {
  die('geht ned');
}

var_dump($matches);