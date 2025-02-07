<?php

namespace App\Traits;

use App\Models\AccessToken;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Auth;

trait Util
{

    public function getRequestDomain()
    {
        $frontendDomain = request()->headers->get('origin') ?? request()->headers->get('referer');
        if (!$frontendDomain) {
            try {
                $frontendDomain = $_SERVER['HTTP_ORIGIN']
                    ?? $_SERVER['HTTP_REFERER']
                    ?? gethostbyaddr($_SERVER['REMOTE_ADDR'])
                    ?? null;
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
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
