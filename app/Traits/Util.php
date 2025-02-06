<?php

namespace App\Traits;

use App\Models\AccessToken;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Auth;

trait Util
{

    public function getRequestDomain()
    {
        // $ip = $_SERVER['REMOTE_ADDR'];
        // return gethostbyaddr($ip);
        $frontendDomain = request()->headers->get('origin') ?? request()->headers->get('referer');
        return $this->getDomainFromUrl($frontendDomain);
    }

    public function getTokenDomain()
    {
        $token = request()->bearerToken();
        $accessToken = AccessToken::findToken($token);
        return $this->getDomainFromUrl($accessToken->domain);
    }

    public function getDomainFromUrl($url)
    {
        // Ensure the URL has a scheme, default to http:// if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        $host = null;

        try {
            $parsedUrl = parse_url($url);

            $host = $parsedUrl['host'] ?? '';

            if (!empty($host)) {
                return $host;
            }
        } catch (\Throwable $th) {
        }

        return $host;
    }
}
