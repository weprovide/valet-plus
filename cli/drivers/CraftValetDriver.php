<?php

class CraftValetDriver extends ValetDriver
{
    public function configure($devtools, $url) {
        info('Configuring Craft');

        $sitePath = getcwd();
        $siteConfigured = $this->isConfigured($devtools, $sitePath);
        $databaseName = $devtools->mysql->getDirName();
        $envFile = $sitePath.'/.env';

        if(!$this->envExists($sitePath) || !$siteConfigured) {
            // Read source configuration from site if it exists
            $sourceFile = $this->envExists($sitePath) ? $envFile : __DIR__.'/../stubs/craft/env';
            info('.env is either missing or misconfigured. Configuring .env file...');
            $devtools->files->putAsUser(
                $envFile,
                str_replace(
                    ['DB_DATABASE=""', 'DB_PASSWORD=""' ],
                    ['DB_DATABASE="' . $databaseName .'"', 'DB_PASSWORD="root"' ],
                    $devtools->files->get($sourceFile)
                )
            );
        }
        // Check if security key is set, set one up if it is not
        if(preg_match('/SECURITY_KEY=""/', $devtools->files->get($envFile)) === 1) {
            info('SECURITY_KEY is not set. Setting SECURITY_KEY in .env');
            $devtools->files->putAsUser(
                $sitePath.'/.env',
                str_replace(
                    'SECURITY_KEY=""',
                    'SECURITY_KEY="'. $this->random_str(32) .'"',
                    $devtools->files->get($envFile)
                )
            );
        }

        if (!in_array($databaseName, $devtools->mysql->getDataBases(false))) {
            info('Creating database');
            if ($devtools->mysql->createDatabase($devtools->mysql->getDirName())) {
                info('Database created');
            } else {
                warning('Could not create database');
            }
        }


        info('Configured Craft');
    }

    public function isConfigured($devtools, $sitePath) {
        if (!file_exists($sitePath.'/.env')) {
            return false;
        }
        return (
            (preg_match('/DB_DATABASE="\S+"/', $devtools->files->get($sitePath.'/.env')) === 1)
            && (preg_match('/DB_PASSWORD="\S+"/', $devtools->files->get($sitePath.'/.env')) === 1)
            && (preg_match('/SECURITY_KEY="\S+"/', $devtools->files->get($sitePath.'/.env')) === 1)
        );
    }
    public function envExists($sitePath) {
        return file_exists($sitePath.'/.env');
    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     *
     * Copied from https://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length      How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     */
    function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

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
        return file_exists($sitePath.'/craft');
    }

    /**
     * Determine the name of the directory where the front controller lives.
     *
     * @param  string  $sitePath
     * @return string
     */
    public function frontControllerDirectory($sitePath)
    {
        return is_file($sitePath.'/craft') ? 'web' : 'public';
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
        $frontControllerDirectory = $this->frontControllerDirectory($sitePath);

        if ($this->isActualFile($staticFilePath = $sitePath.'/'.$frontControllerDirectory.$uri)) {
            return $staticFilePath;
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
        $frontControllerDirectory = $this->frontControllerDirectory($sitePath);

        // Default index path
        $indexPath = $sitePath.'/'.$frontControllerDirectory.'/index.php';
        $scriptName = '/index.php';

        // Check if the first URL segment matches any of the defined locales
        $locales = [
            'ar',
            'ar_sa',
            'bg',
            'bg_bg',
            'ca_es',
            'cs',
            'cy_gb',
            'da',
            'da_dk',
            'de',
            'de_at',
            'de_ch',
            'de_de',
            'el',
            'el_gr',
            'en',
            'en_as',
            'en_au',
            'en_bb',
            'en_be',
            'en_bm',
            'en_bw',
            'en_bz',
            'en_ca',
            'en_dsrt',
            'en_dsrt_us',
            'en_gb',
            'en_gu',
            'en_gy',
            'en_hk',
            'en_ie',
            'en_in',
            'en_jm',
            'en_mh',
            'en_mp',
            'en_mt',
            'en_mu',
            'en_na',
            'en_nz',
            'en_ph',
            'en_pk',
            'en_sg',
            'en_shaw',
            'en_tt',
            'en_um',
            'en_us',
            'en_us_posix',
            'en_vi',
            'en_za',
            'en_zw',
            'en_zz',
            'es',
            'es_cl',
            'es_es',
            'es_mx',
            'es_us',
            'es_ve',
            'et',
            'fi',
            'fi_fi',
            'fil',
            'fr',
            'fr_be',
            'fr_ca',
            'fr_ch',
            'fr_fr',
            'fr_ma',
            'he',
            'hr',
            'hr_hr',
            'hu',
            'hu_hu',
            'id',
            'id_id',
            'it',
            'it_ch',
            'it_it',
            'ja',
            'ja_jp',
            'ko',
            'ko_kr',
            'lt',
            'lv',
            'ms',
            'ms_my',
            'nb',
            'nb_no',
            'nl',
            'nl_be',
            'nl_nl',
            'nn',
            'nn_no',
            'no',
            'pl',
            'pl_pl',
            'pt',
            'pt_br',
            'pt_pt',
            'ro',
            'ro_ro',
            'ru',
            'ru_ru',
            'sk',
            'sl',
            'sr',
            'sv',
            'sv_se',
            'th',
            'th_th',
            'tr',
            'tr_tr',
            'uk',
            'vi',
            'zh',
            'zh_cn',
            'zh_tw',
        ];
        $parts = explode('/', $uri);

        if (count($parts) > 1 && in_array($parts[1], $locales)) {
            $indexPath = $sitePath.'/public/'. $parts[1] .'/index.php';
            $scriptName = '/' . $parts[1] . '/index.php';
        }

        $_SERVER['SCRIPT_FILENAME'] = $indexPath;
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['PHP_SELF'] = $scriptName;
        $_SERVER['DOCUMENT_ROOT'] = $sitePath.'/'.$frontControllerDirectory;        

        return $indexPath;
    }
}
