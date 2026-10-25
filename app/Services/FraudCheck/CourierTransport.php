<?php

namespace App\Services\FraudCheck;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Courier hosts sometimes drop a connection before they answer (cURL 35,
 * 52, 56). On a synchronous request Guzzle reports a reset as a
 * RequestException with no response, so checking for ConnectionException
 * alone never retries it.
 */
final class CourierTransport
{
    public static function shouldRetry(Throwable $exception): bool
    {
        $dropped = $exception instanceof ConnectionException
            || $exception instanceof ConnectException
            || ($exception instanceof GuzzleRequestException && ! $exception->hasResponse());

        if (! $dropped) {
            return false;
        }

        // A request that already used its whole timeout is not sent again.
        return ! str_contains(strtolower($exception->getMessage()), 'operation timed out');
    }

    /**
     * Error text for logs. Request URLs carry the customer's phone number.
     */
    public static function safeMessage(Throwable $exception): string
    {
        $message = preg_replace('/(?:\+?88)?01[3-9]\d{8}/', '01XXXXXXXXX', $exception->getMessage()) ?? '';

        return mb_substr($message, 0, 200);
    }
}
