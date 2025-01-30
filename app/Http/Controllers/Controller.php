<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Crypt;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, ApiResponseTrait, Util;

    public function decodeToken($encryptedToken)
    {
        try {
            return Crypt::decryptString($encryptedToken);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Handle decryption error (e.g., log the error, throw an exception, etc.)
            return null;
        }
    }

    public function encodeToken($token)
    {
        return Crypt::encryptString($token);
    }

}
