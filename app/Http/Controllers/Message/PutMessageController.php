<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\WsMessage;
use Illuminate\Http\Request;

class PutMessageController extends Controller
{
    public function putMessage(Request $request)
    {
        $ids = WsMessage::query()->pluck('dataId')->toArray() ?? [];
        $messages = [];
        foreach ($request->messages as $key => $message) {
            $dataId = $message['dataId'];
            if (!in_array($dataId, $ids)) {
                unset($message['images']);
                $messages[] = $message;
            }
        }
        WsMessage::insert($messages);
    }
}
