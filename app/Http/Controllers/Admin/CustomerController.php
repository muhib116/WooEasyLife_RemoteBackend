<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('id', 'desc')->get();
        return Inertia::render('Customer/Index', compact('customers'));
    }

    public function filter(Request $request)
    {
        $query = Customer::query();
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        $customers = $query->get();
        return response()->json($customers ?? []);
    }

    public function getAddress($id)
    {
        $addresses = CustomerAddress::where('customer_id', $id)->get();
        return $addresses ?? [];
    }

    public function save(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required'
        ]);
        Customer::updateOrCreate([
            'id' => $request->id
        ], $request->all());
        return back()->with('success', 'Customer created successfully');
    }

    public function delete($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return back()->with('error', 'Customer not found');
        }
        $customer->delete();
        return back()->with('success', 'Customer delete successfully');
    }
}
