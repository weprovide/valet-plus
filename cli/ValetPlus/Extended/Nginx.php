<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Nginx as ValetNginx;

class Nginx extends ValetNginx
{
    /**
     * @inheritdoc
     */
    public function installServer(): void
    {
        parent::installServer();

        // Merge fastcgi_params from Laravel Valet with our optimizations.
        $contents = $this->files->get(BREW_PREFIX . '/etc/nginx/fastcgi_params');
        $contents .= $this->files->get(__DIR__ . '/../../stubs/nginx/fastcgi_params');

        $this->files->putAsUser(
            BREW_PREFIX . '/etc/nginx/fastcgi_params',
            $contents
        );
    }
}
