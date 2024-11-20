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

    public function check(PathaoUserSuccessRateRequest $request)
    {
        $phone = $request->phone;

        $confirm_order = 0;
        $cancel_order = 0;
        $response = null;
        if ($phone) {
            try {
                $response = json_decode($this->checkOnSteadfast($phone));
                $confirm_order = @$response[0];
                $cancel_order = $response[1];
            } catch (\Throwable $th) {
            }
        }

        /**
         * To Get User's success rate using phone number
         * @type <POST>
         * @param string $phone
         */
        $pathao_response = PathaoCourier::GET_USER_SUCCESS_RATE($request);

        $pathao_data = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 100,
        ];

        if (!$pathao_response['data']['is_new']) {
            $data = $pathao_response['data'];
            $pathao_data['success_rate'] = $data['success_rate'];
            if (isset($data['customer'])) {
                $customer = $data['customer'];
                $pathao_data['total_order'] = $customer['total_delivery'];
                $pathao_data['confirmed'] = $customer['successful_delivery'];
                $pathao_data['cancel'] = $pathao_data['total_order'] - $pathao_data['confirmed'];
            }
        }

        return response()->json([
            'steadfast_response' => $response,
            'pathao_response' => $pathao_data,
            'confirmed' => $confirm_order,
            'cancel' => $cancel_order,
            'paperfly' => json_decode($this->checkOnPaperFly($phone))
        ]);
    }

    private function checkOnSteadfast($phone)
    {
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

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        return $response;
    }



    private function checkOnPaperFly($phone)
    {
        $url = 'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/list.php?';

        // Create a new cURL resource
        $ch = curl_init($url);

        // Set the request headers
        $headers = [
            'accept: application/json, text/plain, */*',
            'accept-language: en-US,en;q=0.9',
            'authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3MzIxMzgyNzAsImlzcyI6ImxvY2FsaG9zdCIsIm5iZiI6MTczMjEzODI3MCwiZXhwIjoxNzMyMjExOTk5LCJ1c2VybmFtZSI6ImMxNjc2NjAiLCJkZXZpY2VJZGVudGlmaWVyIjoidW5kZWZpbmVkIn0.gXFwNTCgmvkd32h0-sXNDxfT9ThXjA6EE1aXYXpBvOc',
            'content-type: application/json',
            'cookie: _ga=GA1.1.1330688403.1732138592; _ga_VRFXKXNXYT=GS1.1.1732138592.1.0.1732138598.0.0.0; _hjSessionUser_3161698=eyJpZCI6ImYyMTFmYmEwLWM5YjMtNTE1Mi1hNTViLTk5OWUzMWM2OWIyZSIsImNyZWF0ZWQiOjE3MzIxMzg1OTkwNzIsImV4aXN0aW5nIjpmYWxzZX0=; _hjSession_3161698=eyJpZCI6IjE1NGVhZDkyLTg5OTQtNGYwMS1hMjg2LTQ5YjE3YmFkM2MyOSIsImMiOjE3MzIxMzg1OTkwNzMsInMiOjAsInIiOjAsInNiIjowLCJzciI6MCwic2UiOjAsImZzIjoxLCJzcCI6MH0=; token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3MzIxMzgyNzAsImlzcyI6ImxvY2FsaG9zdCIsIm5iZiI6MTczMjEzODI3MCwiZXhwIjoxNzMyMjExOTk5LCJ1c2VybmFtZSI6ImMxNjc2NjAiLCJkZXZpY2VJZGVudGlmaWVyIjoidW5kZWZpbmVkIn0.gXFwNTCgmvkd32h0-sXNDxfT9ThXjA6EE1aXYXpBvOc',
            'device_identifier: undefined',
            'device_name: undefined',
            'origin: https://go.paperfly.com.bd',
            'priority: u=1, i',
            'referer: https://go.paperfly.com.bd/',
            'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "macOS"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-site',
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ];

        // Set the request payload
        $data = [
            'search_text' => $phone,
            'limit' => 50,
            'page' => 1
        ];

        // Configure the cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the request
        $response = curl_exec($ch);

        // Close the cURL session
        curl_close($ch);

        return $response;
    }
}
