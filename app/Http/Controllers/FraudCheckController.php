<?php

namespace App\Http\Controllers;

use App\LogHelper;
use Enan\PathaoCourier\APIBase\PathaoAuth;
use Enan\PathaoCourier\Facades\PathaoCourier;
use Enan\PathaoCourier\Requests\PathaoUserSuccessRateRequest;
use Enan\PathaoCourier\Services\StandardResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class FraudCheckController extends Controller
{
    public function index()
    {
        return Inertia::render('FraudCheck/Index');
    }

    public function saveSteadfastCurl(Request $request)
    {
        if ($request->curl_text) {
            file_put_contents(__DIR__ . '/curlcode.txt', $request->curl_text);
        }
        return back()->with('success', 'Steadfast CURL code is saved successfully!');
    }

    public function expire()
    {
        // PathaoCourier::GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT()
        $time_left = null;
        $steadfast_curl = file_get_contents(__DIR__ . '/curlcode.txt');
        return Inertia::render('FraudCheck/Expire', compact('time_left', 'steadfast_curl'));
    }
    public function getExpire()
    {
        $time_left = PathaoCourier::GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT();
        return $time_left;
    }
    public function renewExpire()
    {
        // $pAuth = PathaoAuth::getNewAccessToken();
        // $pAuth = new PathaoAuth;
        // $pAuth->getNewAccessToken();
        // return 'Hi';
        // return DB::table(env('PATHAO_DB_TABLE_NAME'))
        // ->where('secret_token', '=', env('PATHAO_SECRET_TOKEN'))
        // ->get();
        $pathao_data = DB::table(env('PATHAO_DB_TABLE_NAME'))
            ->where('secret_token', '=', env('PATHAO_SECRET_TOKEN'))->first();
        $headers = [
            "accept" => "application/json",
            "content-type" => 'application/json',
        ];
        $data = [
            "client_id" => config('pathao-courier.pathao_client_id'),
            "client_secret" => config('pathao-courier.pathao_client_secret'),
            "grant_type" => config('pathao-courier.pathao_grant_type_password'),
            "username" => 'naturalcare.help@gmail.com',
            "password" => '8a1!$H$9',
        ];
        // return $data;
        $httpUrl = 'https://api-hermes.pathao.com/aladdin/api/v1/issue-token';
        $httpClient = Http::withHeaders($headers);
        $pathaoResponse = $httpClient->post($httpUrl, $data);

        $token = Arr::get($pathaoResponse, 'access_token');
        $refresh_token = Arr::get($pathaoResponse, 'refresh_token');
        $expires_in = time() + Arr::get($pathaoResponse, 'expires_in');

        $newToken = [
            "token" => $token,
            "refresh_token" => $refresh_token,
            "expires_in" => $expires_in,
            "updated_at" => now(),
        ];
        $isUpdated = false;
        if ($token && $refresh_token) {
            DB::table(env('PATHAO_DB_TABLE_NAME'))
                ->where('secret_token', '=', env('PATHAO_SECRET_TOKEN'))
                ->update($newToken);
            $isUpdated = true;
        }

        return [
            'message' => 'Token has been renewed successfully!',
            'token' => $newToken,
            'db_update_status' => $isUpdated
        ];
    }

    private function getReport(PathaoUserSuccessRateRequest $request, $phone)
    {
        $steadfast_response = $this->checkOnSteadfast($phone);
        $pathao_response = $this->checkOnPathao($request);
        $paper_fly_response = $this->checkOnPaperFly($phone);

        $total_order = ceil(($steadfast_response['total_order'] + $pathao_response['total_order'] + $paper_fly_response['total_order']));
        $confirm_order = ceil(($steadfast_response['confirmed'] + $pathao_response['confirmed'] + $paper_fly_response['confirmed']));

        $success_rate = $total_order == 0 ? 'No order history found!' : ceil(($confirm_order / $total_order) * 100) . '%';
        $response_data = [
            'total_order' => $total_order,
            'confirmed' => $confirm_order,
            'cancel' => ceil(($steadfast_response['cancel'] + $pathao_response['cancel'] + $paper_fly_response['cancel'])),
            'success_rate' => $success_rate,

            'courier' => [
                [
                    'title' => 'Stead Fast',
                    'report' => $steadfast_response
                ],
                [
                    'title' => 'Pathao',
                    'report' => $pathao_response
                ],
                [
                    'title' => 'Paper Fly',
                    'report' => $paper_fly_response
                ],
            ]
        ];
        return $response_data;
    }

    private function checkMultiple($numbers, $cb = null)
    {
        $users = [];
        foreach ($numbers as $number) {
            $request = new PathaoUserSuccessRateRequest();
            $request->merge(['phone' => $number['phone']]);
            $report = $this->getReport($request, $number['phone']);
            $users[] = [
                ...$number, // return all keys that comes throw number.
                'report' => $report
            ];
            $cb && $cb($users);
        }
        return $users;
    }

    public function check(Request $request)
    {
        $phone = $request->phone;
        if (is_array(@$request->data)) {
            return $this->successResponse($this->checkMultiple($request->data));
        } else {
            $request = new PathaoUserSuccessRateRequest();
            $request->merge(['phone' => $phone]);
            $response = $this->getReport($request, $phone);
            return response()->json($response);
        }
    }

    protected function sendEvent(string $event, string $data)
    {
        echo "event: $event\n";
        echo "data: " . $data . "\n\n";
    }

    public function checkStream(Request $request)
    {
        return response()->stream(function () use ($request) {
            $total = count($request->data);
            $processed = 0;

            foreach ($request->data as $number) {
                $processed++;

                $pathaoRequest = new PathaoUserSuccessRateRequest();
                $pathaoRequest->merge(['phone' => $number['phone']]);
                $report = $this->getReport($pathaoRequest, $number['phone']);

                // Send user report
                $progress = ($processed / $total) * 100;
                $this->sendEvent('user_report', json_encode([
                    "data" => [
                        'id' => $number['id'],
                        'phone' => $number['phone'],
                        'report' => $report
                    ],
                    "progress"  => ['processed' => $processed, 'total' => $total, 'percentage' => $progress]
                ]));

                ob_flush();
                flush();
                usleep(100000); // Optional delay
            }

            // Send final done event
            $this->sendEvent('done', json_encode(['message' => 'All processing complete']));
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function checkOnPathao(PathaoUserSuccessRateRequest $request)
    {
        $pathao_data = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
        ];

        $response = null;
        try {
            /**
             * To Get User's success rate using phone number
             * @type <POST>
             * @param string $phone
             */
            $pathao_response = PathaoCourier::GET_USER_SUCCESS_RATE($request);
            // $pAuth = PathaoAuth::getNewAccessToken()
            // $pAuth = new PathaoAuth;
            // getNewAccesstoken
            // DB::table($this->table_name)
            //         ->where('secret_token', '=', $this->pathao_token_data->secret_token)
            //         ->update($response);

            // $pathao_data['time_left'] = PathaoCourier::GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT();
            // GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT

            if (!$pathao_response['data']['is_new']) {
                $data = $pathao_response['data'];
                $pathao_data['success_rate'] = 'No order history found!';
                if (isset($data['customer'])) {
                    $customer = $data['customer'];
                    $pathao_data['total_order'] = $customer['total_delivery'];
                    $pathao_data['confirmed'] = $customer['successful_delivery'];
                    $pathao_data['cancel'] = $pathao_data['total_order'] - $pathao_data['confirmed'];
                    $pathao_data['success_rate'] = $pathao_data['total_order'] == 0 ? 'No order history found!' : ceil(($pathao_data['confirmed'] / $pathao_data['total_order']) * 100) . '%';
                }
            }
        } catch (\Throwable $th) {
            LogHelper::saveLog('Pathao froad check error', $th->getMessage());
            if ($response) {
                LogHelper::saveLog('Pathao froad check error resposne', $response);
            }
        }

        return $pathao_data;
    }

    private function checkOnSteadfast($phone)
    {
        $response_data = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
        ];
        $response = null;
        try {
            $response = Http::get('https://steadfast.com.bd');

            $headers = @$response->headers()['Set-Cookie'] ?? [];
            $parts = explode(';', $headers[0]);

            // Extract the XSRF-TOKEN key-value part
            $tokenPart = explode('=', $parts[0]);

            // Extract the token value
            $xsrfToken = $tokenPart[1];

            $curl_string = file_get_contents(__DIR__ . '/curlcode.txt');

            // $curl = curl_init();
            // curl_setopt_array($curl, [
            //     CURLOPT_URL => "https://steadfast.com.bd/user/frauds/check/" . $phone,
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 30,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "GET",
            //     CURLOPT_HTTPHEADER => [
            //         "accept: application/json, text/plain, */*",
            //         "accept-language: en-US,en;q=0.9",
            //         "cookie: remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d=eyJpdiI6ImZKclk5ZHhtTzZPcU1KWVVSUzU3Z0E9PSIsInZhbHVlIjoiYWtxZDhzR2Y1dVM4Z2RpZUx2UjhDSW1XUFBnOTh1VXJNalJYbGQ3QndLalB3R3Y3VG5lWG9zakJzOFJCemczOUlHZmZXa3FCVkppWjdPRlJadW5vK3FMVkxJc2d3VEtTbW5XSTZSWXI3cFhmMGR5c3pTclJPREZGaytoallPdzhLSm5tdi9Xa2VyOU5tdzFZcUdtSUYrU095K0NlZGx2MmdDZXVINFhVeXd2SUhRREFmNkZ4elcrNWxXcVk4a3hpVWxhQ0pMZmh6YXB3THAwWjVGWWhSbjZGYURJcDI5cW9qSG54NkR3YnVXQT0iLCJtYWMiOiIzMjRlYjM2ZjIwMzg0NjhlNGRiMzI1MWVlZDUzZGUyNmRiMmEzMWQyZGNiNDMyMGVjMzI2NjJlZDQ4ZjU1OTgwIiwidGFnIjoiIn0%3D; XSRF-TOKEN=" . urlencode($xsrfToken) . "; steadfast_merchant_session=eyJpdiI6ImtpRDdtMDlmdlE5SWF6Rmo1dkJUQWc9PSIsInZhbHVlIjoiZHUxZGI2b21JUytjbVZQSVE1ZnQ0ZC9iNkU1SlRtaGE0ZXpNT2xRckhsazRUMjAvWjVDZ3NFWDEzYkVpdDZrY0tlRmJRS3NwdjN5citDOGJ3MmFzVWFTaUo0Z0htdlZjeVBDUDA1aE8rTURKM1IrR2U1bEdRZ3JGVlJ1NjduK2MiLCJtYWMiOiI2NjAyMjg0N2Y0Mjg3Y2E0NTliODNhOTA1ZDBkMTMzMzQ4NDRkNzczNDFiZWUwMTM1MTE5ZjYwODUyNmQyMjA1IiwidGFnIjoiIn0%3D",
            //         "priority: u=1, i",
            //         "referer: https://steadfast.com.bd/user/frauds/check",
            //         'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Google Chrome";v="138"',
            //         "sec-ch-ua-mobile: ?0",
            //         "sec-ch-ua-platform: \"macOS\"",
            //         "sec-fetch-dest: empty",
            //         "sec-fetch-mode: cors",
            //         "sec-fetch-site: same-origin",
            //         "user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36",
            //         "x-requested-with: XMLHttpRequest",
            //         "x-xsrf-token: " . $xsrfToken
            //     ],
            // ]);

            // $curl_response = curl_exec($curl);
            // $err = curl_error($curl);

            // curl_close($curl);
            $curl_response = "{}";
            $curl_string = preg_replace(
                '#https://steadfast\.com\.bd/user/frauds/check/\d+#',
                "https://steadfast.com.bd/user/frauds/check/" . $phone,
                $curl_string
            );
            // 3️⃣ Remove unwanted cookies from -b argument
            $curl_string = preg_replace_callback(
                "/-b\s+'([^']+)'/",
                function ($matches) {
                    $cookieStr = $matches[1];
                    $cookies = explode(';', $cookieStr);
                    $filtered = [];
                    foreach ($cookies as $cookie) {
                        $cookie = trim($cookie);
                        if (
                            stripos($cookie, '_fbp=') === 0 ||
                            stripos($cookie, '_ga=') === 0 ||
                            stripos($cookie, '_gid=') === 0 ||
                            stripos($cookie, 'cf_clearance=') === 0
                        ) {
                            continue; // Skip this cookie
                        }
                        $filtered[] = $cookie;
                    }
                    $newCookieStr = implode('; ', $filtered);
                    return "-b '$newCookieStr'";
                },
                $curl_string
            );
            // 4️⃣ Replace the `x-xsrf-token` header with your new value
            $curl_string = preg_replace(
                "/-H\s+'x-xsrf-token:[^']*'/",
                "-H 'x-xsrf-token: $xsrfToken'",
                $curl_string
            );

            $command = $curl_string;

            // Add -s if not present already
            if (!preg_match('/\s\-s\b/', $command)) {
                $command = preg_replace('/^curl\s/', 'curl -s ', $command);
            }
            $curl_response = shell_exec($command);

            $response = json_decode($curl_response);
            $response_data['some'] = $response;

            LogHelper::saveLog('hi', $curl_response);
            $confirm_order = @$response->total_delivered;
            $cancel_order = @$response->total_cancelled;
            $total_order = $cancel_order + $confirm_order;
            $response_data['total_order'] = $total_order;
            $response_data['confirmed'] = $confirm_order;
            $response_data['cancel'] = $cancel_order;
            $response_data['success_rate'] = $total_order == 0 ? 'No order history found!' : ceil(($confirm_order / $total_order) * 100) . '%';
        } catch (\Throwable $th) {
            $response_data['errrr'] = $th->getMessage();
            LogHelper::saveLog('steadfast froad check error', $th->getMessage());
            if ($response) {
                LogHelper::saveLog('steadfast froad check error resposne', $response);
            }
        }

        return $response_data;
    }

    private function checkOnPaperFly($phone)
    {
        $response_data = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
        ];

        $url = "https://go-app.paperfly.com.bd/merchant/api/react/smart-check/list.php";

        // Headers
        $headers = [
            "accept" => "application/json, text/plain, */*",
            "accept-language" => "en-US,en;q=0.9",
            "authorization" => "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3MzcxNjIxMzksImlzcyI6ImxvY2FsaG9zdCIsIm5iZiI6MTczNzE2MjEzOSwiZXhwIjoxNzM3MjIzMTk5LCJ1c2VybmFtZSI6ImMxNjc2NjAiLCJkZXZpY2VJZGVudGlmaWVyIjoiZjg3YmRmYjQtMmE1NC1lOWNiLTg1ZWEtNjFkNzJjY2VhNmNiIn0.xlXuEhOKYWCZCqWa5rBDv0Drm4S5NFfFMta2jOgxWoo",
            "content-type" => "application/json",
            // "cookie" => "_ga=GA1.1.1330688403.1732138592; _hjSessionUser_3161698=eyJpZCI6ImYyMTFmYmEwLWM5YjMtNTE1Mi1hNTViLTk5OWUzMWM2OWIyZSIsImNyZWF0ZWQiOjE3MzIxMzg1OTkwNzIsImV4aXN0aW5nIjp0cnVlfQ==; _ga_VRFXKXNXYT=GS1.1.1736982728.6.0.1736982730.0.0.0; _hjSession_3161698=eyJpZCI6IjVkNTkzNTczLWI3NjgtNDRjOS04OTBlLTk2NWVlNjgyYzI1MCIsImMiOjE3MzY5ODI3MzE0MjcsInMiOjAsInIiOjAsInNiIjowLCJzciI6MCwic2UiOjAsImZzIjowLCJzcCI6MH0=; token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3MzY5ODI3MzQsImlzcyI6ImxvY2FsaG9zdCIsIm5iZiI6MTczNjk4MjczNCwiZXhwIjoxNzM3MDUwMzk5LCJ1c2VybmFtZSI6ImMxNjc2NjAiLCJkZXZpY2VJZGVudGlmaWVyIjoiZjg3YmRmYjQtMmE1NC1lOWNiLTg1ZWEtNjFkNzJjY2VhNmNiIn0.rBV7AWmjLwk8Yo9MBcbGAlwstYzllhTW0FMUd_yYduo",
            "device_identifier" => "f87bdfb4-2a54-e9cb-85ea-61d72ccea6cb",
            "device_name" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
            "origin" => "https://go.paperfly.com.bd",
            "priority" => "u=1, i",
            "referer" => "https://go.paperfly.com.bd/",
            "sec-ch-ua" => "\"Google Chrome\";v=\"131\", \"Chromium\";v=\"131\", \"Not_A Brand\";v=\"24\"",
            "sec-ch-ua-mobile" => "?0",
            "sec-ch-ua-platform" => "\"macOS\"",
            "sec-fetch-dest" => "empty",
            "sec-fetch-mode" => "cors",
            "sec-fetch-site" => "same-site",
            "user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
        ];

        // Request body
        $body = [
            "search_text" => "01770989591",
            "limit" => 50,
            "page" => 1,
        ];

        // Send POST request
        $response = null;
        try {
            $response = Http::withHeaders($headers)->post($url, $body);
            $jsonResponse = $response->json();
            $records = collect($jsonResponse['records']);
            $delivered = $records->sum('delivered');
            $returned = $records->sum('returned');
            $total_order = $delivered + $returned;
            $success_rate = $total_order == 0 ? 'No order history found!' : ceil(($delivered / $total_order) * 100) . '%';
            $response_data['total_order'] = $total_order;
            $response_data['confirmed'] = $delivered;
            $response_data['cancel'] = $returned;
            $response_data['success_rate'] = $success_rate;
        } catch (\Throwable $th) {
            // LogHelper::saveLog('paperfly froad check error', $th->getMessage());
            // if ($response) {
            //     LogHelper::saveLog('paperfly froad check error resposne', $response);
            // }
        }

        return $response_data;
    }
}
