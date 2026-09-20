<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MetaConnectionController extends Controller
{
    public function __construct(
        protected MetaConnectionService $metaConnections
    ) {
    }

    /**
     * POST /api/v1/meta/connect
     * Body: { license_key, access_token }
     */
    public function connect(Request $request): JsonResponse
    {
        $licenseKey = trim((string) $request->input('license_key', ''));
        $accessToken = trim((string) $request->input('access_token', ''));

        if ($licenseKey === '' || $accessToken === '') {
            return $this->errorResponse('license_key and access_token are required.', 422);
        }

        try {
            $result = $this->metaConnections->connect($licenseKey, $accessToken);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            Log::error('Meta connect failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Unable to connect Meta Business. Please try again.', 500);
        }

        return $this->successResponse([
            'connected' => true,
            'status' => $result['connection']->status,
            'granted_scopes' => $result['granted_scopes'],
            'accounts' => $result['accounts'],
            'pages' => $result['pages'],
            'last_verified_at' => optional($result['connection']->last_verified_at)?->toIso8601String(),
        ], 'Meta Business connected.');
    }

    /**
     * GET /api/v1/meta/accounts?license_key=
     */
    public function accounts(Request $request): JsonResponse
    {
        $licenseKey = trim((string) $request->query('license_key', $request->input('license_key', '')));

        if ($licenseKey === '') {
            return $this->errorResponse('license_key is required.', 422);
        }

        try {
            $accounts = $this->metaConnections->getAccounts($licenseKey);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        } catch (Throwable $exception) {
            Log::error('Meta accounts list failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Unable to list Meta ad accounts.', 500);
        }

        return $this->successResponse([
            'accounts' => $accounts,
        ], 'Meta ad accounts.');
    }

    /**
     * GET /api/v1/meta/pages?license_key=
     */
    public function pages(Request $request): JsonResponse
    {
        $licenseKey = trim((string) $request->query('license_key', $request->input('license_key', '')));

        if ($licenseKey === '') {
            return $this->errorResponse('license_key is required.', 422);
        }

        try {
            $pages = $this->metaConnections->getPages($licenseKey);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        } catch (Throwable $exception) {
            Log::error('Meta pages list failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Unable to list Meta pages.', 500);
        }

        return $this->successResponse([
            'pages' => $pages,
        ], 'Meta pages.');
    }
}
