<?php

use Illuminate\Support\Facades\Crypt;

if (!function_exists('stringLimit')) {
    function stringLimit($string, $limit = 200)
    {
        $decodedDescription = html_entity_decode($string);

        $plainText = strip_tags($decodedDescription);

        return $limit == false ? $plainText : str()->limit($plainText, $limit, '...');
    }
}

if (!function_exists('convertErrorArrayToString')) {
    function convertErrorArrayToString($errors)
    {
        if (! is_array($errors) && ! $errors instanceof \Illuminate\Support\MessageBag) {
            // If the input is neither an array nor an instance of MessageBag, return an original array.
            return $errors;
        }

        $formattedErrors = [];
        foreach ($errors->toArray() as $field => $error) {
            if (is_array($error) && count($error) > 0) {
                $formattedErrors[$field] = $error[0];
            } elseif (is_string($error)) {
                $formattedErrors[$field] = $error;
            }
        }

        return $formattedErrors;
    }
}

if (!function_exists('saveLog')) {
    function saveLog($message, $file = null)
    {
        $path = 'logs/';
        $path .= $file ? $file : 'common.log';
        $log_path = storage_path($path);
        if ($message) {
            try {
                if (is_array($message)) {
                    error_log("[" . date('Y-m-d H:i:s') . '] ' . json_encode($message) . "\n", 3, $log_path);
                } else {
                    error_log("[" . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $log_path);
                }
            } catch (\Throwable $th) {
            }
        }
    }
}

function generateKey($prefix = 'SF')
{
    return $prefix . time();
}

if (!function_exists('getLog')) {
    function getLog($file = null)
    {
        $path = 'logs/';
        $path .= $file ? $file : 'common.log';
        $log_path = storage_path($path);
        if (file_exists($log_path)) {
            return file_get_contents($log_path);
        }
    }
}

function decodeToken($encryptedToken)
{
    try {
        return Crypt::decryptString($encryptedToken);
    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
        // Handle decryption error (e.g., log the error, throw an exception, etc.)
        return null;
    }
}

function encodeToken($token)
{
    return Crypt::encryptString($token);
}

function generateUniqueApiKey()
{
    $randomString = str()->random(16);
    $timestamp = microtime(true);
    $hash = hash_hmac('md5', $randomString . $timestamp, config('app.key'));
    return $hash;
}
