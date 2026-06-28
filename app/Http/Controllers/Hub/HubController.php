<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\AccessToken;
use App\Models\PackageUseHistory;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use App\Traits\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HubController extends Controller
{
    use Transaction;

    public function hubUse(Request $request, DomainNormalizer $domainNormalizer)
    {
        $rules = [
            'order_count' => 'required|integer|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            LogHelper::saveLog('validation error on hub use', json_encode($validator->errors()));
            return $this->validationErrorResponse($validator->errors());
        }

        $user = User::find(Auth::id());
        $token = $request->bearerToken();
        $accessToken = AccessToken::findToken($token);

        if (! $accessToken || ! $accessToken->status) {
            LogHelper::saveLog('Order limit over', 'Invalid or disabled license token');
            return $this->errorResponse('Invalid Token', 401);
        }

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            LogHelper::saveLog('Order limit over', 'Expired license token');
            return $this->errorResponse('Expired', 401);
        }

        $host = $this->getDomainFromUrl($accessToken->domain);
        if (!$host) {
            LogHelper::saveLog('invalid domain', 'domain mismatch');
            return $this->errorResponse('Invalid domain', 401);
        }

        $package = UserPackage::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get()
            ->first(fn (UserPackage $userPackage) => $domainNormalizer->matches(
                $userPackage->domain,
                $accessToken->domain
            ));

        if (! $package) {
            LogHelper::saveLog('Order limit over', 'No active package found');
            return $this->errorResponse('No package found or no remaining order', 400, null, true);
        }

        if ($package->expires_at && now()->greaterThan($package->expires_at)) {
            LogHelper::saveLog('Order limit over', 'Subscription expired');
            return $this->errorResponse('Your subscription plan has expired.', 400, null, true);
        }

        if ($package->remaining_order <= 0) {
            LogHelper::saveLog('Order limit over', 'Quota exhausted');
            return $this->errorResponse('No package found or no remaining order', 400, null, true);
        }

        if ($request->order_count > $package->remaining_order) {
            LogHelper::saveLog('Order limit over', 'Order count exceeds remaining quota');
            return $this->errorResponse('Order count exceeds remaining quota', 400, null, true);
        }

        try {
            $total_order_handled = $package->total_order_handled;
            $remaining_order = $package->remaining_order;

            DB::beginTransaction();
            $cost = round($package->per_order_rate * $request->order_count, 2);
            $useDetails = null;
            try {
                $useDetails = $request->use_details;
            } catch (\Throwable $th) {
                LogHelper::saveLog('Package use_details parse error', $th->getMessage());
            }
            $total_order_handled = $total_order_handled + $request->order_count;
            $remaining_order = $remaining_order - $request->order_count;
            $data = [
                'user_id' => Auth::id(),
                'user_package_id' => $package->id,
                'use_details' => $useDetails,
                'order_count' => $request->order_count,
                'cost' => $cost + 0,
                'total_order_handled' => $total_order_handled,
                'remaining_order' => $remaining_order,
                'created_by' => Auth::id(),
                // 'updated_by' => '',
            ];
            $packageUse = PackageUseHistory::create($data);
            $package->update([
                'total_order_handled' => $total_order_handled,
                'remaining_order' => $remaining_order,
            ]);
            $packageUse->transactionHistory()->create([
                'user_id' => Auth::id(),
                'created_by' => Auth::id(),
                'amount' => - ($cost + 0),
                'type' => 'out',
            ]);
            DB::commit();

            return $this->successResponse($packageUse, 'History stored successfully');
        } catch (\Throwable $th) {
            LogHelper::saveLog('Package use error', $th->getMessage());
            return $this->errorResponse($th->getMessage());
        }
    }
}
