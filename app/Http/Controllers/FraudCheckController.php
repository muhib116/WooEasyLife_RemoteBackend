<?php

namespace App\Http\Controllers;

use Enan\PathaoCourier\Facades\PathaoCourier;
use Enan\PathaoCourier\Requests\PathaoUserSuccessRateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class FraudCheckController extends Controller
{
    public function index()
    {
        return Inertia::render('FraudCheck/Index');
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
        // if (is_array(@$request->data)) {
        //     return $this->successResponse($this->checkMultiple($request->data));
        // } else {
        //     $request = new PathaoUserSuccessRateRequest();
        //     $request->merge(['phone' => $phone]);
        //     $response = $this->getReport($request, $phone);
        //     return response()->json($response);
        // }
    }

    private function checkOnPathao(PathaoUserSuccessRateRequest $request)
    {
        $pathao_data = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
        ];

        try {
            /**
             * To Get User's success rate using phone number
             * @type <POST>
             * @param string $phone
             */
            $pathao_response = PathaoCourier::GET_USER_SUCCESS_RATE($request);

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
        try {
            $response = Http::get('https://steadfast.com.bd');

            $headers = @$response->headers()['Set-Cookie'] ?? [];
            $parts = explode(';', $headers[0]);

            // Extract the XSRF-TOKEN key-value part
            $tokenPart = explode('=', $parts[0]);

            // Extract the token value
            $xsrfToken = $tokenPart[1];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://steadfast.com.bd/user/frauds/check/" . $phone,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "accept: application/json, text/plain, */*",
                    "accept-language: en-US,en;q=0.9",
                    "cookie: remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d=eyJpdiI6Inh0VFpKTXFYSzJ6VEJ5d0RCVHE1b2c9PSIsInZhbHVlIjoiTkFKaGNSM1ZvTit0VmYxa01LRXZKcjFqc1QxSTNkckxqUk0ya1Y3aEUvUnVFMFhyUHE2WkFjLzV5UGtRd1ptS1dGa3FxeVNiOHROSHFLQXBMQytkcW16eUxYeWVhZ3llL3YzM2szOWIxTnJBcURMclRmc1NDVWNhdzhXa1B0WDVMK0tFakJqVmplK29OMUlrYW9QeXJMYWtSVlpKbXRaVnAvN05HOHpTb1VYWGJQdi93RTVxOGZnZjBvb0hmZWFjYk0zb09QSEUxbFRSSGxjZXJialNKbS9iZmdGbVFOZ0FrMVFhVC9DcitJaz0iLCJtYWMiOiI0OWMxYTgzNjAyMjcxMGUxOGY3NDg3N2ZlYTYyZDE2NDVmZDQyYmJiMmI0YTI1NTcxMWYyNTk4YWNjNzJiMmI3IiwidGFnIjoiIn0%3D; cf_clearance=qYEGknO8p1SD7pc39hAEPdqyWQU.uzgVyiz_BJ3f2rg-1732108103-1.2.1.1-i30GzOXCwmAncFgAcRF9LJFGW4.8JCpy5_pUNU4PSqA_ioIsRB_4CCoM6MozhmjKU7.iPYDZN2dQH2YJRGo08zllyFLn3T6ttt81xXEu4O8Ze36hoDsZn.KU4nXK02RV1AFEjZSE0n2wHSxJIEOrhRsZ5UibWj.KI_Vo1bEBiC.H7fAF.2Ia4.KB.HnPHbzBnw0e4DLnEU60R0MTge8wLrXGotCG5QroWgi4sUzRO1et74SyGIbniK6Rqc6jtX7jHE8PMTtG5ZRaaT39MEgLUHKY_A00uWkm5g9VWKvZF3mkz10cG1EgkmgUvtBT.guGQ7vvcQyDwLqhQqpTgSTZI.eT3JhzLDoZaNWQC0KOufrZaqmQXQYtqE7Y9LMgTBolb32PVcORvRdHEFddntBpOw; XSRF-TOKEN=eyJpdiI6IjZpTUNKVkgrNkwrdVVlUExySjFRN1E9PSIsInZhbHVlIjoiYmlPVm9qVjFqb21JcFlMTjJPSDVSZ2d6Y0ZqSG12allrN1c3TlRhOHZSSjNhbXJVNiszV1JSWGFkUEVueUhGVUg2RzJybSszZU5xM2JSMzE1enpZVjR6UTZvSDAwSHgyWTZzWElybGdpdGV6L21zOVZ0MzBwblVDUklHU0VWYkoiLCJtYWMiOiI0NTNjM2M3MTI2MWJkYjZlNjM1ZDkzOThhYWFmN2Y1MTg3ODQxN2VlZGFlODAwOGViMzQ3Mjk2YjRlNmJkNDIzIiwidGFnIjoiIn0%3D; steadfast_merchant_session=eyJpdiI6Ik5zcHJLSXphcGFUVEl0TlZiYVA5Wnc9PSIsInZhbHVlIjoic0wvRzBnbmdKbEdIeUJwZ1VySkRiYmlRSFVqNDY2aXM2WHJvQkpIaTA0ZWZrTnA2RnZMbmQ2bmdiYnpGcUdzVzUzZUpaYjlWZSs0YWVFbi95N1BuTGtoVjhWd01pNW8vRkN1WjUxRnpsR1ZjeVpEZ1M3QkR3bUJKVEFTUWhSeXEiLCJtYWMiOiJkZTBjMDk4YjU1NTFmODg3NDA2MTI5OThjMWE0ZWEwMjI3NjFiZmE5YTFjOTMzMWQzY2FjM2ViZDUwNjgzMzI2IiwidGFnIjoiIn0%3D",
                    "priority: u=1, i",
                    "referer: https://steadfast.com.bd/user/frauds/check",
                    "sec-ch-ua: \"Google Chrome\";v=\"131\", \"Chromium\";v=\"131\", \"Not_A Brand\";v=\"24\"",
                    "sec-ch-ua-mobile: ?0",
                    "sec-ch-ua-platform: \"macOS\"",
                    "sec-fetch-dest: empty",
                    "sec-fetch-mode: cors",
                    "sec-fetch-site: same-origin",
                    "user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
                    "x-requested-with: XMLHttpRequest",
                    "x-xsrf-token: " . $xsrfToken
                ],
            ]);

            $curl_response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $response = json_decode($curl_response);
            $confirm_order = @$response[0];
            $cancel_order = $response[1];
            $total_order = $cancel_order + $confirm_order;
            $response_data['total_order'] = $total_order;
            $response_data['confirmed'] = $confirm_order;
            $response_data['cancel'] = $cancel_order;
            $response_data['success_rate'] = $total_order == 0 ? 'No order history found!' : ceil(($confirm_order / $total_order) * 100) . '%';
        } catch (\Throwable $th) {
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
        }

        return $response_data;
    }
}
