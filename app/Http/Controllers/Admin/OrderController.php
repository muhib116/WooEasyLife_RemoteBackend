<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index() {
        $customers = Customer::orderBy('id', 'desc')->get();
        return Inertia::render('Order/Index', compact('customers'));
    }
}