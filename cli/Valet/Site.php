<?php

namespace Valet;

class Site
{
    var $config, $cli, $files;

    /**
     * Create a new Site instance.
     *
     * @param  Configuration  $config
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     */
    function __construct(Configuration $config, CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
        $this->config = $config;
    }

    /**
     * Get the real hostname for the given path, checking links.
     *
     * @param  string  $path
     * @return string|null
     */
    function host($path)
    {
        foreach ($this->files->scandir($this->sitesPath()) as $link) {
            if ($resolved = realpath($this->sitesPath().'/'.$link) === $path) {
                return $link;
            }
        }

        return basename($path);
    }

    /**
     * Link the current working directory with the given name.
     *
     * @param  string $target
     * @param  string $link
     * @return string
     */
    function link($target, $link)
    {
        $tld = $this->config->read()['domain'];
        $link = str_replace('.'.$tld, '', $link);
        $this->files->ensureDirExists(
            $linkPath = $this->sitesPath(), user()
        );

        $this->config->prependPath($linkPath);

        $this->files->symlinkAsUser($target, $linkPath.'/'.$link);

        return $link.'.'.$tld;
    }

    /**
     * Pretty print out all links in Valet.
     *
     * @param string $filterName
     * @return \Illuminate\Support\Collection
     */
    function links($filterName = '') {
        $certsPath = VALET_HOME_PATH.'/Certificates';

        $this->files->ensureDirExists($certsPath, user());

        $certs = $this->getCertificates($certsPath);

        return $this->getLinks(VALET_HOME_PATH.'/Sites', $certs, $filterName);
    }

    /**
     * Get all certificates from config folder.
     *
     * @param string $path
     * @return \Illuminate\Support\Collection
     */
    function getCertificates($path)
    {
        return collect($this->files->scanDir($path))->filter(function ($value, $key) {
            return ends_with($value, '.crt');
        })->map(function ($cert) {
            return substr($cert, 0, -8);
        })->flip();
    }

    /**
     * Get list of links and present them formatted.
     *
     * @param string $path
     * @param \Illuminate\Support\Collection $certs
     * @param $filterName
     * @return \Illuminate\Support\Collection
     */
    function getLinks($path, $certs, $filterName = false)
    {
        $config = $this->config->read();
        $tld = $config['domain'];

        return collect($this->files->scanDir($path))->mapWithKeys(function ($site) use ($path) {
            return [$site => $this->files->readLink($path.'/'.$site)];
        })->map(function ($path, $site) use ($certs, $config, $tld, $filterName) {
            $secured = $certs->has($site);
            $url = ($secured ? 'https': 'http').'://'.$site.'.'.$tld;

            if($filterName) {
                $site = str_replace('.'.$filterName, '', $site);
            } else {
                $site = $site.'.'.$tld;
            }

            return [$site, $secured ? ' X': '', $url, $path];
        })->filter(function ($item) use ($filterName, $tld) {
            if(!$filterName) {
                return true;
            }

            return strstr($item[2], '.'.$filterName.'.'.$tld);
        });
    }

    /**
     * Unlink the given symbolic link.
     *
     * @param  string  $name
     * @return void
     */
    function unlink($name)
    {
        if ($this->files->exists($path = $this->sitesPath().'/'.$name)) {
            $this->files->unlink($path);
        }
    }

    /**
     * Remove all broken symbolic links.
     *
     * @return void
     */
    function pruneLinks()
    {
        $this->files->ensureDirExists($this->sitesPath(), user());

        $this->files->removeBrokenLinksAt($this->sitesPath());
    }

    /**
     * Resecure all currently secured sites with a fresh domain.
     *
     * @param  string  $oldDomain
     * @param  string  $domain
     * @return void
     */
    function resecureForNewDomain($oldDomain, $domain)
    {
        if (! $this->files->exists($this->certificatesPath())) {
            return;
        }

        $secured = $this->secured();

        foreach ($secured as $url) {
            $proxy = $this->proxied($url);
            $this->unsecure($url);
            $this->configure(str_replace('.'.$oldDomain, '.'.$domain, $url), true, $proxy);
        }
    }

    /**
     * Retrieves the proxy destination if there is one.
     *
     * @param string $url
     * @return null|string
     */
    function proxied($url)
    {
        $path = VALET_HOME_PATH.'/Nginx/'.$url;
        if (!$this->files->exists($path)) {
            return null;
        }

        if (preg_match('/proxy_pass (.*);/', $this->files->get($path), $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Configures the domain to proxy to a destination. Null disables the proxy.
     *
     * @param string $url
     * @param string $to
     */
    function proxy($url, $to = null)
    {
        $this->configure($url, in_array($url, $this->secured()), $to);
    }

    /**
     * Get all of the URLs that are currently secured.
     *
     * @return array
     */
    function secured()
    {
        return collect($this->files->scandir($this->certificatesPath()))
                    ->map(function ($file) {
                        return str_replace(['.key', '.csr', '.crt', '.conf'], '', $file);
                    })->unique()->values()->all();
    }

    /**
     * Configures the site with secure, unsecure, and proxy.
     *
     * @param string $url
     * @param bool $secure
     * @param null $proxy
     * @return void
     */
    function configure($url, $secure = false, $proxy = null)
    {
        $this->unsecure($url);

        if ($secure) {
            $this->files->ensureDirExists($this->certificatesPath(), user());
            $this->createCertificate($url);
        }

        $this->files->putAsUser(
            VALET_HOME_PATH . '/Nginx/' . $url, $this->buildNginxConfig($url, $secure, $proxy)
        );
    }

    /**
     * Secure the given host with TLS.
     *
     * @param  string $url
     * @return void
     */
    function secure($url)
    {
        $proxied = $this->proxied($url);
        $this->configure($url, true, $proxied);
    }

    /**
     * Create and trust a certificate for the given URL.
     *
     * @param  string  $url
     * @return void
     */
    function createCertificate($url)
    {
        $keyPath = $this->certificatesPath().'/'.$url.'.key';
        $csrPath = $this->certificatesPath().'/'.$url.'.csr';
        $crtPath = $this->certificatesPath().'/'.$url.'.crt';
        $confPath = $this->certificatesPath().'/'.$url.'.conf';

        $this->buildCertificateConf($confPath, $url);
        $this->createPrivateKey($keyPath);
        $this->createSigningRequest($url, $keyPath, $csrPath, $confPath);

        $this->cli->runAsUser(sprintf(
            'openssl x509 -req -days 365 -in %s -signkey %s -out %s -extensions v3_req -extfile %s',
            $csrPath, $keyPath, $crtPath, $confPath
        ));

        $this->trustCertificate($crtPath);
    }

    /**
     * Create the private key for the TLS certificate.
     *
     * @param  string  $keyPath
     * @return void
     */
    function createPrivateKey($keyPath)
    {
        $this->cli->runAsUser(sprintf('openssl genrsa -out %s 2048', $keyPath));
    }

    /**
     * Create the signing request for the TLS certificate.
     *
     * @param  string  $keyPath
     * @return void
     */
    function createSigningRequest($url, $keyPath, $csrPath, $confPath)
    {
        $this->cli->runAsUser(sprintf(
            'openssl req -new -key %s -out %s -subj "/C=/ST=/O=/localityName=/commonName=*.%s/organizationalUnitName=/emailAddress=/" -config %s -passin pass:',
            $keyPath, $csrPath, $url, $confPath
        ));
    }

    /**
     * Trust the given certificate file in the Mac Keychain.
     *
     * @param  string  $crtPath
     * @return void
     */
    function trustCertificate($crtPath)
    {
        $this->cli->run(sprintf(
            'sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain %s', $crtPath
        ));
    }

    /**
     * Build the SSL config for the given URL.
     *
     * @param  string  $url
     * @return string
     */
    function buildCertificateConf($path, $url)
    {
        $config = str_replace('VALET_DOMAIN', $url, $this->files->get(__DIR__.'/../stubs/openssl.conf'));
        $this->files->putAsUser($path, $config);
    }

    /**
     * Builds the nginx configuration file for a site.
     * @param string $url
     * @param bool $secure
     * @param null|string $proxy
     * @return string
     */
    function buildNginxConfig($url, $secure, $proxy)
    {
        $path = $this->certificatesPath();

        $variables = [
            'VALET_HOME_PATH' => VALET_HOME_PATH,
            'VALET_SERVER_PATH' => VALET_SERVER_PATH,
            'VALET_STATIC_PREFIX' => VALET_STATIC_PREFIX,
            'VALET_SITE' => $url,
            'VALET_CERT' => $path.'/'.$url.'.crt',
            'VALET_KEY' => $path.'/'.$url.'.key',
            'VALET_PROXY_PASS' => $proxy,
        ];

        $stub = 'valet.conf';
        $proxy && $stub = 'proxy.'.$stub;
        $secure && $stub = 'secure.'.$stub;

        return str_replace(
            array_keys($variables),
            array_values($variables),
            $this->files->get(__DIR__.'/../stubs/' . $stub)
        );
    }

    /**
     * Unsecure the given URL so that it will use HTTP again.
     *
     * @param  string  $url
     * @return void
     */
    function unsecure($url)
    {
        if ($this->files->exists($this->certificatesPath().'/'.$url.'.crt')) {
            $this->files->unlink(VALET_HOME_PATH.'/Nginx/'.$url);

            $this->files->unlink($this->certificatesPath().'/'.$url.'.conf');
            $this->files->unlink($this->certificatesPath().'/'.$url.'.key');
            $this->files->unlink($this->certificatesPath().'/'.$url.'.csr');
            $this->files->unlink($this->certificatesPath().'/'.$url.'.crt');

            $this->cli->run(sprintf('sudo security delete-certificate -c "%s" -t', $url));
        }
    }

    /**
     * Get the path to the linked Valet sites.
     *
     * @return string
     */
    function sitesPath()
    {
        return VALET_HOME_PATH.'/Sites';
    }

    /**
     * Get the path to the Valet TLS certificates.
     *
     * @return string
     */
    function certificatesPath()
    {
        return VALET_HOME_PATH.'/Certificates';
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    function rewrites()
    {
        $config = $this->config->read();
        $rewrites = [];

        if (isset($config['rewrites']) && isset($config['rewrites'])) {
            foreach ($config['rewrites'] as $site => $_rewrites) {
                foreach ($_rewrites as $rewrite) {
                    $rewrites[] = [$site, $rewrite];
                }
            }
        }

        return collect($rewrites);
    }

    /**
     * @param $url
     * @param $host
     * @return bool|string
     */
    function rewrite($url, $host)
    {
        $url    = (strpos($url, 'www.') === 0 ? substr($url, 4) : $url);
        $config = $this->config->read();

        // Store config
        if (!isset($config['rewrites'])) {
            $config['rewrites'] = [];
        }
        if (!isset($config['rewrites'][$host])) {
            $config['rewrites'][$host] = [];
        }
        if (in_array($url, $config['rewrites'][$host])) {
            return false;
        }
        $config['rewrites'][$host][] = $url;
        $this->config->write($config);

        // Add rewrite to /etc/hosts file
        $this->files->append('/etc/hosts', "\n127.0.0.1  www.$url  $url");

        return $url;
    }

    /**
     * @param $url
     * @return bool|string
     */
    function unrewrite($url)
    {
        $url    = (strpos($url, 'www.') === 0 ? substr($url, 4) : $url);
        $config = $this->config->read();
        if (isset($config['rewrites'])) {
            // Remove from config
            foreach ($config['rewrites'] as $site => $rewrites) {
                $config['rewrites'][$site] = array_filter(array_diff($config['rewrites'][$site], [$url]));
            }
            $config['rewrites'] = array_filter($config['rewrites']);
            $this->config->write($config);

            // Remove from /etc/hosts file
            $hosts = $this->files->get('/etc/hosts');
            $hosts = str_replace("\n127.0.0.1  www.$url  $url", "", $hosts);
            $this->files->put('/etc/hosts', $hosts);

            return $url;
        }

        return false;
    }
}
