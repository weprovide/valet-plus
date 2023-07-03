<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\DriverConfigs;

use WeProvide\ValetPlus\DriverConfigurator;
use function Valet\info;
use function Valet\output;

class Shopware5ValetDriver extends DriverConfigurator
{
    /** @var string */
    protected const SHOPWARE5_ENV_STUB = __DIR__ . '/../../stubs/drivers/shopware5/.env.dist';

    /**
     * @inheritdoc
     */
    public function configure()
    {
        $path   = $this->getPath();
        $dir    = $this->getDir();
        $domain = $this->getDomain();

        info('Configuring Shopware 5... ');

        if (!($envExists = $this->envExists($path))) {
            output("\tInstalling default .env (missing)...");
            $this->files->putAsUser(
                $path . '/.env',
                str_replace(
                    [
                        'DOMAIN',
                        'DBNAME'
                    ],
                    [
                        $domain,
                        $dir
                    ],
                    $this->files->get(static::SHOPWARE5_ENV_STUB)
                )
            );
        }
    }

    /**
     * Returns whether the .env exists.
     *
     * @param string $path
     * @return bool
     */
    public function envExists(string $path)
    {
        return file_exists($path . '/.env');
    }
}
