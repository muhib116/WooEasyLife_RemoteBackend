<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CurlController extends Controller
{
    public function index()
    {
        // $response = Http::withHeaders([
        //     'accept' => 'application/json, text/plain, */*',
        //     'accept-language' => 'en-US,en;q=0.9',
        //     'priority' => 'u=1, i',
        //     'sec-ch-ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        //     'sec-ch-ua-mobile' => '?0',
        //     'sec-ch-ua-platform' => '"macOS"',
        //     'sec-fetch-dest' => 'empty',
        //     'sec-fetch-mode' => 'cors',
        //     'sec-fetch-site' => 'same-origin',
        //     'x-requested-with' => 'XMLHttpRequest',
        //     'x-xsrf-token' => 'eyJpdiI6IjRLZytWTEVMNm5SdzJSK1pWM1F3MXc9PSIsInZhbHVlIjoiSjFNL1JlOEhjYlY3dDdzYzZNU1VPSnJCK2pUM3VSMmNNbWlxUlFQRkY1VDRxanF1SUV0WmtpZ2lsb2g0WUdpakJDOFhqTXZtQnJHVWJzMnZzMTY3dy9zRCtsN2syMHdUb2h1Y3c2cFJWZWFndkV5UFh5UEJwdUJrYU1uSUUyc28iLCJtYWMiOiIyNjAyYzYzMmYwNjExYjc4NGRmZTRjZjU0ZjZkNGIzOThiMTBmYTZmMzgwZGQyNDk3YTc0NzZiN2ViNTFkZThlIiwidGFnIjoiIn0='
        // ])->withOptions([
        //     'referrer' => 'https://steadfast.com.bd/user/frauds/check',
        //     'referrer_policy' => 'strict-origin-when-cross-origin',
        // ])->get('https://steadfast.com.bd/user/frauds/check/01752360254');
        $response = Http::get('https://steadfast.com.bd');

        $headers = @$response->headers()['Set-Cookie'] ?? [];
        $parts = explode(';', $headers[0]);

        // Extract the XSRF-TOKEN key-value part
        $tokenPart = explode('=', $parts[0]);

        // Extract the token value
        $xsrfToken = $tokenPart[1];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://steadfast.com.bd/user/frauds/check/01770989591",
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
}
