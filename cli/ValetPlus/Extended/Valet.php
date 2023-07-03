<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Valet as ValetValet;

class Valet extends ValetValet
{
    /**
     * @todo: check if this is needed
     * Symlink the Valet Bash script into the user's local bin.
     */
    public function symlinkToUsersBin(): void
    {
//        $this->unlinkFromUsersBin();
//
//        $this->cli->runAsUser('ln -s "' . realpath(__DIR__ . '/../../../valet') . '" ' . $this->valetBin);
    }
}
