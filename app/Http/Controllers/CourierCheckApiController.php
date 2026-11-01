<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourierCheck\CheckCourierCheckRequest;
use App\Http\Requests\CourierCheck\RegisterCourierCheckRequest;
use App\LogHelper;
use App\Services\CourierCheck\CourierCheckApiService;
use App\Services\CourierCheck\CourierCheckException;
use App\Services\FraudCheck\CourierTransport;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

class CourierCheckApiController extends Controller
{
    public function __construct(
        private CourierCheckApiService $courierCheckApi,
    ) {}

    public function register(RegisterCourierCheckRequest $request): JsonResponse
    {
        try {
            return response()->json($this->courierCheckApi->register((string) $request->input('site_url')));
        } catch (CourierCheckException $exception) {
            return response()->json(['error' => $exception->error], $exception->status);
        }
    }

    public function check(CheckCourierCheckRequest $request): JsonResponse
    {
        set_time_limit(120);

        try {
            return response()->json($this->courierCheckApi->check(
                (string) $request->input('phone'),
                (string) $request->input('site_url'),
                (string) $request->input('site_key'),
            ));
        } catch (CourierCheckException $exception) {
            $body = ['error' => $exception->error];
            if ($exception->error === 'daily_limit') {
                $body['resets_at'] = $this->courierCheckApi->resetsAt();
            }

            return response()->json($body, $exception->status);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'invalid_phone'], 422);
        } catch (Throwable $exception) {
            LogHelper::saveLog('Courier check API error', CourierTransport::safeMessage($exception));

            return response()->json(['error' => 'check_failed'], 500);
        }
    }
}
