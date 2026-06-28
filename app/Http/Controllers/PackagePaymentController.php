<?php

namespace App\Http\Controllers;

use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Services\PackagePaymentService;
use App\Services\PackagePlanResolver;
use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PackagePaymentController extends Controller
{
    use ApiResponseTrait, Util;

    public function __construct(
        protected PackagePaymentService $packagePaymentService,
        protected PackagePlanResolver $planResolver
    ) {
    }

    public function plans()
    {
        $plans = $this->planResolver->mapPlansPayload(
            $this->packagePaymentService->listActivePlans()
        );

        return $this->successResponse($plans);
    }

    public function createRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_hub_id' => 'required|integer',
            'order_limit' => 'nullable|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'transaction_charge' => 'nullable|numeric|min:0',
            'total_charge' => 'nullable|numeric|min:0',
            'account_number' => 'required|string',
            'transaction_id' => 'required|string',
            'transaction_method' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $user = User::find(Auth::id());
        $token = $request->bearerToken();
        $accessToken = \App\Models\AccessToken::findToken($token);

        try {
            $paymentRequest = $this->packagePaymentService->createRequest(
                $user,
                [
                    ...$request->all(),
                    'domain' => $this->getTokenDomain(),
                ],
                $accessToken
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        return $this->successResponse($paymentRequest, 'Payment request submitted successfully.');
    }

    public function history(Request $request)
    {
        $userId = Auth::id();
        $domain = $this->getTokenDomain();

        $query = PackagePaymentRequest::query()
            ->with('packageHub:id,title,per_order_rate,package_price,order_rate_token,package_duration')
            ->where('user_id', $userId)
            ->orderByDesc('id');

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $requests = $query->get()
            ->filter(fn (PackagePaymentRequest $paymentRequest) => app(\App\Services\DomainNormalizer::class)->matches(
                $paymentRequest->domain,
                $domain
            ))
            ->values();

        return $this->successResponse($requests);
    }
}
