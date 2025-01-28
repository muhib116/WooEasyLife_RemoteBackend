<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\PackageUseHistory;
use App\Models\User;
use App\Models\UserPackage;
use App\Traits\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HubController extends Controller
{
    use Transaction;

    public function hubUse(Request $request)
    {
        $rules = [
            'data' => 'required|array',
            'data.*.order_count' => 'integer|min:0',
            'data.*.use_details' => 'nullable|json'
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        return $request->all();

        $user = User::find(Auth::id());
        $token = $request->bearerToken();
        $accessToken = AccessToken::findToken($token);
        // $host = $this->getDomainFromUrl($accessToken->domain);
        // if (!$host) {
        //     return '';
        // }

        $package = UserPackage::where('user_id', $user->id)
            ->where('remaining_order', '>', 0)
            ->where('domain', $accessToken->domain)
            ->orderBy('id', 'asc')
            ->first();

        if (!$package) {
            // return ''; // some package not found or token end error
        }

        // return $package;
        $data = [
            'user_id' => Auth::id(),
            'user_package_id' => $package->id,
            'use_details' => '',
            'order_count' => '',
            'cost' => '',
            'total_order_handled' => '',
            'remaining_order' => '',
            'created_by' => '',
            'updated_by' => '',
        ];
        return $data;
        // PackageUseHistory::create();
    }
}
