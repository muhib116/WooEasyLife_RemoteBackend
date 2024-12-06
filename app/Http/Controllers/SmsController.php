<?php

namespace App\Http\Controllers;

use App\Models\SmsBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SmsController extends Controller
{

    private function countSmsSegments($text)
    {
        // Define character limits
        $englishLimit = 145; // Characters per SMS for English
        $banglaLimit = 63;   // Characters per SMS for Bangla

        // Regular expression to detect Bangla characters
        $banglaPattern = '/[\x{0980}-\x{09FF}]/u';

        // Separate Bangla and English portions
        $banglaCount = 0;
        $englishCount = 0;

        for ($i = 0; $i < mb_strlen($text); $i++) {
            $char = mb_substr($text, $i, 1);
            if (preg_match($banglaPattern, $char)) {
                $banglaCount++;
            } else {
                $englishCount++;
            }
        }

        // Calculate SMS segments for Bangla and English
        $banglaSegments = ceil($banglaCount / $banglaLimit);
        $englishSegments = ceil($englishCount / $englishLimit);

        // Total SMS count
        return $banglaSegments + $englishSegments;
    }

    public function send(Request $request)
    {
        $apiToken = 'GuN1Tp8ueoRJACAl072B';
        // http://bulksmsbd.net/api/smsapi?api_key=GuN1Tp8ueoRJACAl072B&type=text&number=Receiver&senderid=8809617619992&message=TestSMS

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'regex:/^01[3-9]\d{8}$/'
            ],
            'content' => 'required'
        ]);

        if ($validator->failed()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $isSuccess = false;

        try {
            $url = "http://bulksmsbd.net/api/smsapi";

            $phone = $request->phone;
            $sms = $request->content;
            $data = [
                'api_key'   => $apiToken,
                'type'      => 'text',
                'number'    => $phone,
                'senderid'  => '8809617619992',
                'message'   => $sms
            ];

            // Initialize cURL session
            $ch = curl_init();

            // Configure cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execute the request and fetch the response
            $response = curl_exec($ch);

            $responseDecoded = json_decode($response);

            if ($responseDecoded->message_id && !$responseDecoded->error_message) {
                // add success to content
                $smsLength = strlen($sms);
                $smsCount = $this->countSmsSegments($sms);
                // 63/145
                $data = [
                    'user_id' => Auth::id(),
                    'type' => 'out',
                    'amount' => - ($smsCount * 0.04),
                    'sms_rate' => 0.40,
                    'sms_text' => $sms,
                    'sms_count' => $smsCount,
                    'message_id' => $responseDecoded->message_id,
                    'note' => '',
                ];
                SmsBalance::create($data);
                $isSuccess = true;
            }

            file_put_contents(__DIR__ . '/sms.log', $response);
            // Check for errors
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
                file_put_contents(__DIR__ . '/sms-error.log', 'Error:' . curl_error($ch));
            } else {
            }

            // Close cURL session
            curl_close($ch);
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $this->successResponse($isSuccess, 'Sms sent successfully');
    }
}
