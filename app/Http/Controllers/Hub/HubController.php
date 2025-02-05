<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\AccessToken;
use App\Models\PackageUseHistory;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserPackage;
use App\Traits\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HubController extends Controller
{
    use Transaction;

    public function hubUse(Request $request)
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
        $host = $this->getDomainFromUrl($accessToken->domain);
        if (!$host) {
            LogHelper::saveLog('invalid domain', 'domain mismatch');
            return $this->errorResponse('Invalid domain');
        }

        $package = UserPackage::where('user_id', $user->id)
            ->where('remaining_order', '>', 0)
            ->where('domain', $accessToken->domain)
            ->orderBy('id', 'asc')
            ->first();

        if (!$package) {
            LogHelper::saveLog('Order limit over', 'No package found or no remaining order');
            return $this->errorResponse('No package found or no remaining order', 400, null, true);
        }

        try {
            $total_order_handled = $package->total_order_handled;
            $remaining_order = $package->remaining_order;

            DB::beginTransaction();
            $cost = number_format($package->per_order_rate * $request->order_count, 2);
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
