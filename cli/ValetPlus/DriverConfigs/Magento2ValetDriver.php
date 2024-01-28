<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\DriverConfigs;

use WeProvide\ValetPlus\DriverConfigurator;

use function Valet\info;
use function Valet\output;

class Magento2ValetDriver extends DriverConfigurator
{
    /** @var string */
    protected const MAGENTO2_ENV_STUB = __DIR__ . '/../../stubs/drivers/magento2/env.php';

    /**
     * @inheritdoc
     */
    public function configure()
    {
        $path   = $this->getPath();
        $dir    = $this->getDir();
        $domain = $this->getDomain();
        $url    = $this->getUrl() . '/';

        info('Configuring Magento 2... ');

        if (!($envExists = $this->envExists($path))) {
            output("\tInstalling default env.php (missing)...");
            $this->files->putAsUser(
                $path . '/app/etc/env.php',
                str_replace(
                    [
                        'DOMAIN',
                        'DBNAME',
                        'REDIS',
                        'CRYPT'
                    ],
                    [
                        $domain,
                        $dir,
                        ($this->redis->isEnabled() ? 'redis' : 'files'),
                        bin2hex(random_bytes((32 - (32 % 2)) / 2))
                    ],
                    $this->files->get(static::MAGENTO2_ENV_STUB)
                )
            );
        }

        if (!($configExists = $this->configExists($path))) {
            output("\tEnabling all modules (config.php missing)...");
            $this->cli->quietlyAsUser('bin/magento module:enable --all');
        }

        if (!$envExists) {
            output("\tSetting base url...");
            $this->cli->quietlyAsUser('magerun2 config:set --lock-env web/unsecure/base_url ' . $url);
            $this->cli->quietlyAsUser('magerun2 config:set --lock-env web/secure/base_url ' . $url);

            output("\tEnabling URL rewrites...");
            $this->cli->quietlyAsUser('magerun2 config:set web/seo/use_rewrites 1');
        }

        if (!$envExists || !$configExists) {
            output("\tFlushing cache...");
            $this->cli->quietlyAsUser('magerun2 cache:flush');
        }
    }

    /**
     * Returns whether the env.php exists.
     *
     * @param string $path
     * @return bool
     */
    public function envExists(string $path)
    {
        return file_exists($path . '/app/etc/env.php');
    }

    /**
     * Returns whether the config.php exists.
     *
     * @param string $path
     * @return bool
     */
    public function configExists(string $path)
    {
        return file_exists($path . '/app/etc/config.php');
    }
}
