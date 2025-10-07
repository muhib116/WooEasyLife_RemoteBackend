<?php

namespace App\Console\Commands;

use App\LogHelper;
use App\Models\AccessToken;
use App\Traits\Util;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestScheduler extends Command
{
    use Util;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:scheduler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A test scheduler that runs every minute';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $tokens = AccessToken::all();
        $tokens = collect($tokens)->each(function ($token) {
            // Default status
            $status = 'invalid';

            // 1️⃣ Expired
            if ($token->expires_at && Carbon::parse($token->expires_at)->isPast()) {
                $status = 'expired';
            }
            // 2️⃣ Disabled (status = false)
            elseif (!$token->status) {
                $status = 'unauthenticated';
            }
            // 3️⃣ Otherwise valid
            else {
                $status = 'valid';
            }


            try {
                // Add virtual attribute
                $token->token_status = $status;
                $domain = $this->getDomainFromUrl($token->domain);
                if ($domain != 'localhost') {
                    $url = "https://{$domain}/wp-json/wooeasylife/v1/license-status";
                    $response = Http::timeout(5)->post($url, [
                        'status' => $status,
                    ]);
                    if ($response->successful()) {
                        // LogHelper::saveLog("License status sent to {$domain}", [
                        //     'token_id' => $token->id,
                        //     'status'   => $status,
                        //     'response' => $response->json(),
                        // ]);
                    } else {
                        LogHelper::saveLog("Failed to send license status to {$domain}", [
                            'token_id' => $token->id,
                            'status'   => $status,
                            'response' => $response->body(),
                        ]);
                        $url = "http://{$domain}/wp-json/wooeasylife/v1/license-status";
                        $response = Http::timeout(5)->post($url, ['status' => $status]);
                        if ($response->failed()) {
                            LogHelper::saveLog("Retry failed to send license status to {$domain}", [
                                'token_id' => $token->id,
                                'status'   => $status,
                                'response' => $response->body(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $th) {
                LogHelper::saveLog("Error sending license status for token #{$token->id}: ", $th->getMessage());
            }
        });
    }
}
