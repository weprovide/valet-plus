<?php

/**
 * Define the user's "~/.squire" path.
 */

define('SQUIRE_HOME_PATH', posix_getpwuid(fileowner(__FILE__))['dir'].'/.squire');
define('SQUIRE_STATIC_PREFIX', 'ec955b08-4b80-4524-a966-0098988dd98c');

/**
 * Load the Squire configuration.
 */
$squireConfig = json_decode(
    file_get_contents(SQUIRE_HOME_PATH.'/config.json'), true
);

/**
 * Parse the URI and site / host for the incoming request.
 */
$uri = urldecode(
    explode("?", $_SERVER['REQUEST_URI'])[0]
);

$siteName = basename(
    // Filter host to support xip.io feature
    $_SERVER['HTTP_HOST'],
    '.'.$squireConfig['domain']
);

if (strpos($siteName, 'www.') === 0) {
    $siteName = substr($siteName, 4);
}

/**
 * Determine the fully qualified path to the site.
 */
$squireSitePath = null;
$domain = array_slice(explode('.', $siteName), -1)[0];

foreach ($squireConfig['paths'] as $path) {
    if (is_dir($path.'/'.$siteName)) {
        $squireSitePath = $path.'/'.$siteName;
        break;
    }

    if (is_dir($path.'/'.$domain)) {
        $squireSitePath = $path.'/'.$domain;
        break;
    }
}

if (is_null($squireSitePath)) {
    http_response_code(404);
    require __DIR__.'/cli/templates/404.php';
    exit;
}

$squireSitePath = realpath($squireSitePath);

/**
 * Find the appropriate Squire driver for the request.
 */
$squireDriver = null;

require __DIR__.'/cli/drivers/require.php';

$squireDriver = SquireDriver::assign($squireSitePath, $siteName, $uri);

if (! $squireDriver) {
    http_response_code(404);
    echo 'Could not find suitable driver for your project.';
    exit;
}

/**
 * ngrok uses the X-Original-Host to store the forwarded hostname.
 */
if (isset($_SERVER['HTTP_X_ORIGINAL_HOST']) && !isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = $_SERVER['HTTP_X_ORIGINAL_HOST'];
}

/**
 * Allow driver to mutate incoming URL.
 */
$uri = $squireDriver->mutateUri($uri);

/**
 * Determine if the incoming request is for a static file.
 */
$isPhpFile = pathinfo($uri, PATHINFO_EXTENSION) === 'php';

if ($uri !== '/' && ! $isPhpFile && $staticFilePath = $squireDriver->isStaticFile($squireSitePath, $siteName, $uri)) {
    return $squireDriver->serveStaticFile($staticFilePath, $squireSitePath, $siteName, $uri);
}

/**
 * Attempt to dispatch to a front controller.
 */
$frontControllerPath = $squireDriver->frontControllerPath(
    $squireSitePath, $siteName, $uri
);

if (! $frontControllerPath) {
    http_response_code(404);
    echo 'Did not get front controller from driver. Please return a front controller to be executed.';
    exit;
}

chdir(dirname($frontControllerPath));

unset($domain, $path, $siteName, $uri, $squireConfig, $squireDriver, $squireSitePath);

require $frontControllerPath;
