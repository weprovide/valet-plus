<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Illuminate\Support\Collection;
use JsonException;
use Valet\Site as ValetSite;

class Site extends ValetSite
{
    /**
     * Returns collection with rewritten domains.
     *
     * @return Collection
     * @throws JsonException
     */
    public function rewrites()
    {
        $config   = $this->config->read();
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
     * Add a public domain to rewrite to local environment.
     *
     * @param $url
     * @param $host
     * @return false|string
     * @throws JsonException
     */
    public function rewrite($url, $host)
    {
        $url    = ltrim($url, 'www.');
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

        $this->link(getcwd(), $url);

        return $url;
    }

    /**
     * Removes public domain from rewrites.
     *
     * @param $url
     * @return false|string
     * @throws JsonException
     */
    public function unrewrite($url)
    {
        $url    = ltrim($url, 'www.');
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

            $this->unlink($url);

            return $url;
        }

        return false;
    }
}
