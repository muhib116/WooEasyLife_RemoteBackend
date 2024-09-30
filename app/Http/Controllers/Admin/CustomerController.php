<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index() {
        $customers = Customer::orderBy('id', 'desc')->get();
        return Inertia::render('Customer/Index', compact('customers'));
    }

    public function save(Request $request) {
        $request->validate([
            'name' => 'required',
            'phone' => 'required'
        ]);
        Customer::updateOrCreate([
            'id' => $request->id
        ], $request->all());
        return back()->with('success', 'Customer created successfully');
    }

    public function delete($id) {
        $customer = Customer::find($id);
        if(!$customer) {
            return back()->with('error', 'Customer not found');
        }
        $customer->delete();
        return back()->with('success', 'Customer delete successfully');
    }
}
