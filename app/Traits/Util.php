<?php

namespace App\Traits;

use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Auth;

trait Util
{
    public function getDomainFromUrl($url)
    {
        // Ensure the URL has a scheme, default to http:// if missing
        if (!preg_match('/^https?:\/\//', $url) || !preg_match('/^http?:\/\//', $url)) {
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
