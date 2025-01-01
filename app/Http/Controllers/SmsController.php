<?php

namespace App\Http\Controllers;

use App\Models\SmsBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SmsController extends Controller
{

    public function calculateSMSDetails($message)
    {
        $GSM_7BIT = "GSM_7BIT";
        $GSM_7BIT_EX = "GSM_7BIT_EX";
        $UTF16 = "UTF16";

        $gsm7bitExChar = "\\^{}\\\\\\[~\\]|â‚¬\n";
        $gsm7bitChars = "@Â£$Â¥Ã¨Ã©Ã¹Ã¬Ã²Ã‡\\nÃ˜Ã¸\\rÃ…Ã¥Î_Î¦ÎÎ›Î©Î Î¨Î£Î˜ÎžÃ†Ã¦ÃŸÃ‰ !\\\"#Â¤%&'()*+,-./0123456789:;<=>?Â¡ABCDEFGHIJKLMNOPQRSTUVWXYZÃ„ÃÃ‘ÃœÂ§Â¿abcdefghijklmnopqrstuvwxyzÃ¤Ã¶Ã±Ã¼Ã ";

        $messageLength = [
            $GSM_7BIT => 160,
            $GSM_7BIT_EX => 160,
            $UTF16 => 70
        ];
        $multiMessageLength = [
            $GSM_7BIT => 153,
            $GSM_7BIT_EX => 153,
            $UTF16 => 67
        ];

        $gsm7bitRegExp = "/^[{$gsm7bitChars}]*$/";
        $gsm7bitExRegExp = "/^[{$gsm7bitChars}{$gsm7bitExChar}]*$/";
        $gsm7bitExOnlyRegExp = "/^[\\{$gsm7bitExChar}]*$/";

        function detectEncoding($text)
        {
            global $gsm7bitRegExp, $gsm7bitExRegExp;

            if (preg_match($gsm7bitRegExp, $text)) {
                return "GSM_7BIT";
            } elseif (preg_match($gsm7bitExRegExp, $text)) {
                return "GSM_7BIT_EX";
            }
            return "UTF16";
        }

        function countGsm7bitEx($text)
        {
            global $gsm7bitExOnlyRegExp;
            preg_match_all($gsm7bitExOnlyRegExp, $text, $matches);
            return count($matches[0]);
        }

        // Remove line breaks from the input text
        $props = str_replace(["\r\n", "\n", "\r"], " ", $props);

        // Detect encoding
        $encoding = detectEncoding($props);

        // Get the message length
        $length = strlen($props);
        $per_message = $messageLength[$encoding];
        $messages = 1;
        $remaining = 0;

        if ($encoding === $GSM_7BIT_EX) {
            $length += countGsm7bitEx($props);
        }

        if ($length > $per_message) {
            $per_message = $multiMessageLength[$encoding];
        }

        // Calculate number of messages
        $messages = ceil($length / $per_message);
        $remaining = $per_message * $messages - $length;

        if ($remaining == 0 && $messages == 0) {
            $remaining = $per_message;
        }

        return $messages;

        // return [
        //     'encoding' => $encoding,
        //     'setSmsCharacterCount' => $length,
        //     'setSmsRemainingCount' => $remaining,
        //     'setSmsPartCount' => $messages
        // ];
    }

    private function countSmsSegments($text)
    {
        return $this->calculateSMSDetails($text);
        // Define character limits
        $englishLimit = 160; // Characters per SMS for English
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

        // $isSuccess = false;
        $responseDecoded = [];

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
                // $smsLength = strlen($sms);
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
                // $isSuccess = true;
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

        return $this->successResponse($responseDecoded, 'Sms sent successfully');
    }

    public function recharge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $data = [
            'user_id' => Auth::id(),
            'type' => 'in',
            'amount' => $request->amount,
        ];

        $balance = SmsBalance::create($data);

        return $balance;
    }

    public function rechargeHistory(Request $request)
    {
        $userId = Auth::id();

        $balanceHistory = SmsBalance::query()
            ->where('user_id', $userId)
            ->where('type', 'in')
            ->get();

        return $this->successResponse($balanceHistory);
    }
    public function useHistory(Request $request)
    {
        $userId = Auth::id();

        $query = SmsBalance::query()
            ->where('user_id', $userId)
            ->where('type', 'out');

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $balanceHistory = $query->get()->toArray();

        return $this->successResponse($balanceHistory);
    }

    public function smsBalance()
    {
        $userId = Auth::id();

        $balance = SmsBalance::query()->where('user_id', $userId)->sum('amount');
        return $this->successResponse($balance);
    }
}
