<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\CustomerNotice;
use App\Models\User;
use App\LogHelper;
use App\Services\CustomerNoticeService;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function __construct(
        private CustomerNoticeService $customerNoticeService,
    ) {}

    public function index(Request $request)
    {
        $token = $request->bearerToken();
        $accessToken = AccessToken::findToken($token);

        if (! $accessToken) {
            return $this->errorResponse('Invalid Token', 401);
        }

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            return $this->errorResponse('Expired', 401);
        }

        try {
            $user = User::findForApiAccess((int) $accessToken->tokenable_id);

            if (! $user) {
                return $this->errorResponse('Unauthenticated', 401);
            }

            $notices = $this->customerNoticeService
                ->activeNoticesFor($user, $accessToken->domain)
                ->map(fn (CustomerNotice $notice) => [
                    'id' => $notice->id,
                    'type' => $notice->type,
                    'severity' => $notice->severity,
                    'title' => $notice->title,
                    'body' => $notice->body,
                    'cta_label' => $notice->cta_label,
                    'cta_url' => $notice->cta_url,
                    'is_dismissible' => (bool) $notice->is_dismissible,
                ])
                ->values();

            return $this->successResponse($notices);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Get notices catch', $th->getMessage());

            return $this->errorResponse('Unable to load notices', 400);
        }
    }
}
