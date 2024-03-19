<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use GuzzleHttp\Client;
use Valet\Valet as ValetValet;

class Valet extends ValetValet
{
    /**
     * Determine if this is the latest version of Valet+.
     *
     * @param string $currentVersion
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onLatestPlusVersion(string $currentVersion): bool
    {
        $url = 'https://api.github.com/repos/weprovide/valet-plus/releases/latest';
        $response = json_decode((string) (new Client())->get($url)->getBody());

        return version_compare($currentVersion, trim($response->tag_name, 'v'), '>=');
    }
}
