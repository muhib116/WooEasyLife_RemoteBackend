<?php

namespace App\Http\Controllers;

use App\LogHelper;
use App\Models\SmsBalance;
use App\Models\SmsRecharge;
use App\Traits\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class SmsController extends Controller
{

    use Transaction;

    // public function calculateSMSDetails($message)
    // {
    //     $GSM_7BIT = "GSM_7BIT";
    //     $GSM_7BIT_EX = "GSM_7BIT_EX";
    //     $UTF16 = "UTF16";

    //     $gsm7bitExChar = "\\^{}\\\\\\[~\\]|€\n";
    //     $gsm7bitChars = file_get_contents(__DIR__ . '/Data/smsCharacters.txt');

    //     $messageLength = [
    //         $GSM_7BIT => 160,
    //         $GSM_7BIT_EX => 160,
    //         $UTF16 => 70
    //     ];
    //     $multiMessageLength = [
    //         $GSM_7BIT => 153,
    //         $GSM_7BIT_EX => 153,
    //         $UTF16 => 67
    //     ];

    //     $gsm7bitRegExp = "/^[" . preg_quote($gsm7bitChars, '/') . "]*$/u";
    //     $gsm7bitExRegExp = "/^[" . preg_quote($gsm7bitChars . $gsm7bitExChar, '/') . "]*$/u";
    //     $gsm7bitExOnlyRegExp = "/[" . preg_quote($gsm7bitExChar, '/') . "]/u";

    //     function detectEncoding($text, $gsm7bitRegExp, $gsm7bitExRegExp)
    //     {
    //         if (preg_match($gsm7bitRegExp, $text)) {
    //             return "GSM_7BIT";
    //         } elseif (preg_match($gsm7bitExRegExp, $text)) {
    //             return "GSM_7BIT_EX";
    //         }
    //         return "UTF16";
    //     }

    //     function countGsm7bitEx($text, $gsm7bitExOnlyRegExp)
    //     {
    //         preg_match_all($gsm7bitExOnlyRegExp, $text, $matches);
    //         return is_array($matches[0]) ? count($matches[0]) : 0;
    //     }

    //     // Ensure the message is a string and remove line breaks
    //     $cleanMessage = is_string($message) ? str_replace(["\r\n", "\n", "\r"], " ", $message) : "";

    //     // Detect encoding
    //     $encoding = detectEncoding($cleanMessage, $gsm7bitRegExp, $gsm7bitExRegExp);

    //     // Get the message length
    //     $length = mb_strlen($cleanMessage, 'UTF-8');
    //     $perMessage = $messageLength[$encoding] ?? 160; // Ensure a valid numeric value
    //     $messages = 1;
    //     $remaining = 0;

    //     if ($encoding === $GSM_7BIT_EX) {
    //         $length += countGsm7bitEx($cleanMessage, $gsm7bitExOnlyRegExp);
    //     }

    //     if ($length > $perMessage) {
    //         $perMessage = $multiMessageLength[$encoding] ?? 153; // Ensure numeric value
    //     }

    //     return $perMessage;

    //     // Ensure $length and $perMessage are numbers
    //     $length = (int) $length;
    //     $perMessage = (int) $perMessage;

    //     // Calculate number of messages
    //     $messages = (int) ceil($length / $perMessage);
    //     $remaining = max(($perMessage * $messages) - $length, 0); // Avoid negative values

    //     return [
    //         'encoding' => $encoding,
    //         'setSmsCharacterCount' => $length,
    //         'setSmsRemainingCount' => $remaining,
    //         'setSmsPartCount' => $messages
    //     ];
    // }

    public function getTotalSmsCount($message)
    {
        $GSM_7BIT = "GSM_7BIT";
        $GSM_7BIT_EX = "GSM_7BIT_EX";
        $UTF16 = "UTF16";

        $gsm7bitExChar = "\\^{}\\\\\\[~\\]|€\n";
        $gsm7bitChars = file_get_contents(__DIR__ . '/Data/smsCharacters.txt');

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

        $gsm7bitRegExp = "/^[" . preg_quote($gsm7bitChars, '/') . "]*$/u";
        $gsm7bitExRegExp = "/^[" . preg_quote($gsm7bitChars . $gsm7bitExChar, '/') . "]*$/u";
        $gsm7bitExOnlyRegExp = "/[" . preg_quote($gsm7bitExChar, '/') . "]/u";

        function detectEncoding($text, $gsm7bitRegExp, $gsm7bitExRegExp)
        {
            if (preg_match($gsm7bitRegExp, $text)) {
                return "GSM_7BIT";
            } elseif (preg_match($gsm7bitExRegExp, $text)) {
                return "GSM_7BIT_EX";
            }
            return "UTF16";
        }

        function countGsm7bitEx($text, $gsm7bitExOnlyRegExp)
        {
            preg_match_all($gsm7bitExOnlyRegExp, $text, $matches);
            return is_array($matches[0]) ? count($matches[0]) : 0;
        }

        // Ensure the message is a string and remove line breaks
        $cleanMessage = is_string($message) ? str_replace(["\r\n", "\n", "\r"], " ", $message) : "";

        // Detect encoding
        $encoding = detectEncoding($cleanMessage, $gsm7bitRegExp, $gsm7bitExRegExp);

        // Get the message length
        $length = mb_strlen($cleanMessage, 'UTF-8');
        $perMessage = $messageLength[$encoding] ?? 160; // Ensure valid numeric value

        if ($encoding === $GSM_7BIT_EX) {
            $length += countGsm7bitEx($cleanMessage, $gsm7bitExOnlyRegExp);
        }

        if ($length > $perMessage) {
            $perMessage = $multiMessageLength[$encoding] ?? 153; // Ensure numeric value
        }

        // Ensure $length and $perMessage are numbers
        $length = (int) $length;
        $perMessage = (int) $perMessage;

        // Calculate number of messages
        $messages = (int) ceil($length / $perMessage);
        // $remaining = max(($perMessage * $messages) - $length, 0); // Prevent negative values

        // **Returning only total SMS count as per your requirement**
        return $messages;
    }




    private function countSmsSegments($text)
    {
        return $this->getTotalSmsCount($text);
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
        $apiToken = config('services.bulksms.api_key');

        if (!$apiToken) {
            LogHelper::saveLog('sms provider missing', 'BULKSMS_API_KEY is not configured');
            return $this->errorResponse('SMS provider is not configured.');
        }

        $validator = Validator::make($request->all(), [
            // 'phone' => ['required', function ($attribute, $value, $fail) {
            //     $numbers = explode(',', $value);
            //     foreach ($numbers as $number) {
            //         $number = trim($number);
            //         if (!preg_match('/^01[3-9]\d{8}$/', $number)) {
            //             $fail("The phone number {$number} is not valid.");
            //         }
            //     }
            // }],
            'phone' => [
                'required',
                // 'regex:/^01[3-9]\d{8}$/'
                //                 validate phone.
                // my phone will come like below:

                // "01752360254,01770989591"
            ],
            'content' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $phone = $request->phone;
        $sms = $request->content;
        $smsCount = 1;

        try {
            $smsCount = $this->getTotalSmsCount($sms);
        } catch (\Throwable $th) {
            LogHelper::saveLog('sms counting issue', json_encode([
                'error' => $th->getMessage(),
                'sms' => $sms,
                'phone' => $phone
            ]));
        }


        // check available sms balance
        $userId = Auth::id();

        $balance = SmsBalance::query()->where('user_id', $userId)->sum('amount');

        $amount = ($smsCount * 0.40);


        try {
            $phone_arr = explode(',', $phone);
            if (is_array($phone_arr)) {
                $phn_count = count($phone_arr);
                if ($phn_count) {
                    $amount = $amount * $phn_count;
                    $smsCount = $smsCount * $phn_count;
                }
            }
        } catch (\Throwable $th) {
            LogHelper::saveLog('sms count when multiple phone', 'Phone: (' . $phone . ') ' . $th->getMessage());
        }

        if ($balance <= 0) {
            LogHelper::saveLog('sms balance over 1', 'UserId: ' . $userId . ' sms balance is over');
            return $this->errorResponse('Insufficient SMS balance.');
        }

        if ($balance < $amount) {
            LogHelper::saveLog('sms balance Insufficient', 'UserId: ' . $userId . ' sms balance is over');
            return $this->errorResponse('Insufficient SMS balance.');
        }

        // $isSuccess = false;
        $responseDecoded = new stdClass;
        DB::beginTransaction();
        try {
            $url = "http://bulksmsbd.net/api/smsapi";
            $data = [
                'api_key'   => $apiToken,
                'type'      => 'text',
                'number'    => $phone,
                'senderid'  => config('services.bulksms.sender_id'),
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

            if (@$responseDecoded->message_id && !@$responseDecoded->error_message) {
                // add success to content
                // $smsLength = strlen($sms);
                try {
                    // 63/145
                    $data = [
                        'user_id' => Auth::id(),
                        'type' => 'out',
                        'amount' => - ($amount + 0),
                        'sms_rate' => 0.40,
                        'phone' => $phone,
                        'sms_text' => $sms,
                        'sms_count' => $smsCount,
                        'message_id' => @$responseDecoded->message_id,
                        'note' => '',
                        'domain' => $this->getTokenDomain(),
                        'created_by' => Auth::id(),
                    ];


                    $smsBalance = SmsBalance::create($data);
                    $smsBalance->transactionHistory()->create([
                        'user_id' => Auth::id(),
                        'created_by' => Auth::id(),
                        'amount' => - ($amount + 0),
                        'type' => 'out',
                    ]);
                    DB::commit();
                } catch (\Throwable $th) {
                    LogHelper::saveLog('error while sms balance cut', $th->getMessage());
                }
                // $isSuccess = true;
            } else {
                LogHelper::saveLog('error while sending sms', $response);
            }

            try {
                file_put_contents(__DIR__ . '/sms.log', $response);
            } catch (\Throwable $th) {
            }
            // Check for errors
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
                file_put_contents(__DIR__ . '/sms-error.log', 'Error:' . curl_error($ch));
                LogHelper::saveLog('sms send error', 'Error:' . curl_error($ch));
            } else {
            }

            // Close cURL session
            curl_close($ch);
        } catch (\Throwable $th) {
            DB::rollBack();
            LogHelper::saveLog('sms send error catch', $th->getMessage());
            return $this->errorResponse('Failed to send message');
        }

        return $this->successResponse($responseDecoded, 'Sms sent successfully');
    }

    public function smsAdminRecharge(Request $request, $userId)
    {
        $request->validate([
            'total_amount' => 'required',
            'transaction_charge' => 'required',
            'transaction_method' => 'required',
        ]);

        // transaction charge will be 1.85% of the amount
        // $transactionCharge = round(($request->total_amount * 1.85) / 100, 2);
        $data = [
            'user_id' => $userId,
            'created_by' => Auth::id(),
            'total_amount' => round($request->total_amount ?? 0, 2),
            'transaction_charge' => round($request->transaction_charge ?? 0, 2),
            'transaction_method' => $request->transaction_method,
            'transaction_id' => $request->transaction_id,
            'account_number' => $request->account_number,
            'domain' => $request->domain,
            'status' => 'pending',
        ];

        $recharge = SmsRecharge::create($data);

        return back()->with('success', 'Recharge request submitted successfully.');
    }

    public function recharge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_amount' => 'required',
            'total_charge' => 'required',
            'account_number' => 'required',
            'transaction_id' => 'required',
            'transaction_method' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // transaction charge will be 1.85% of the amount
        // $transactionCharge = round(($request->total_amount * 1.85) / 100, 2);
        $data = [
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'total_amount' => round($request->total_amount, 2),
            'transaction_charge' => round($request->total_charge, 2),
            'transaction_method' => $request->transaction_method,
            'transaction_id' => $request->transaction_id,
            'account_number' => $request->account_number,
            'domain' => $this->getTokenDomain(),
            'status' => 'pending',
        ];

        $recharge = SmsRecharge::create($data);

        return $this->successResponse($recharge);
    }

    public function rechargeHistory(Request $request)
    {
        $userId = Auth::id();

        $query = SmsRecharge::query()
            ->where('user_id', $userId)
            ->where('type', 'in');

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        $balanceHistory = $query->get()->toArray();

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

        $domain = $this->getTokenDomain();
        $balance = SmsBalance::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->sum('amount');
        return $this->successResponse($balance);
    }
}
