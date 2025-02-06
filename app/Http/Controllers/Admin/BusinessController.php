<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBusiness;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function index($userId)
    {
        $domain = $this->getDomainFromUrl("https://b2b.skyflightbd.com/");
        $dnsRecords = dns_get_record($domain, DNS_A);
        dd($dnsRecords);
        $user = User::find($userId);
        return Inertia::render('Users/Business/Index', compact('user'));
    }
    // "host" => "api.wpsalehub.com"
    // "class" => "IN"
    // "ttl" => 4502
    // "type" => "A"
    // "ip" => "198.187.29.19"

    public function store(Request $request, $userId)
    {

        $request->validate([
            'domain' => 'required',
            'title' => 'required'
        ]);

        $user = User::find($userId);

        UserBusiness::create([
            'user_id' => $user->id,
            'domain' => $request->domain,
            'title' => $request->title,
            'description' => $request->description
        ]);
    }
}
