<?php

class Magento2ValetDriver extends ValetDriver
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
    return file_exists($sitePath.'/pub/index.php') &&
    file_exists($sitePath.'/bin/magento');
}

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
    $isMagentoStatic = false;
    $resource = $uri;

    if(strpos($uri, '/static/') !== false) {
        $isMagentoStatic = true;
    }

    if(!$isMagentoStatic && strpos($uri, '/media/') === false) {
        return false;
    }

    if($isMagentoStatic) {
        $resource = preg_replace('#static(/version[0-9]+)?/#', '', $uri, 1);
        $uri = '/static' . $resource;
    }

    if (file_exists($staticFilePath = $sitePath.'/pub'.$uri)) {
        return $staticFilePath;
    }

    if (strpos($uri, '/static/') === 0) {
        $_GET['resource'] = $resource;
        include($sitePath.DIRECTORY_SEPARATOR.'pub'.DIRECTORY_SEPARATOR.'static.php');
        exit;
    }

    if (strpos($uri, '/media/') === 0) {
        include($sitePath.DIRECTORY_SEPARATOR.'pub'.DIRECTORY_SEPARATOR.'get.php');
        exit;
    }

    return false;
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
    return $sitePath.'/pub/index.php';
}
}
