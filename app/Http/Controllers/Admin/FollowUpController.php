<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class FollowUpController extends Controller
{

    public function sendMessage(Request $request) {
        $message = 'hi this is from laravel';
        $userId = $request->input('user_id');
        $followUps = FollowUp::orderBy('id', 'desc')->get();

        $response = Http::post('https://socket.skyflightbd.com/message', [
            'message' => $followUps,
            'userId' => $userId
        ]);

        return response()->json($response->json());
    }
    
    public function index() {
        $followUps = FollowUp::orderBy('id', 'desc')->get();
        return Inertia::render('FollowUp/Index', compact('followUps'));
    }
    public function followUp($id) {
        $customer = Customer::find($id);
        $followUps = FollowUp::where('customer_id', $id)->orderBy('id', 'desc')->get();
        return Inertia::render('FollowUp/Index', compact('customer', 'followUps'));
    }

    public function save(Request $request, $customer_id) {
        $request->validate([
            'title' => 'required',
        ]);
        $data = [
            'customer_id' => $customer_id,
            'title' => $request->title,
            'description' => $request->description,
            'next_follow_topic' => $request->next_follow_topic,
            'follow_date' => $request->follow_date,
            'next_follow_date' => $request->next_follow_date,
        ];

        FollowUp::updateOrCreate(['id' => $request->id], $data);
        
        return back()->with('success', 'Saved successfully');
    }
}
