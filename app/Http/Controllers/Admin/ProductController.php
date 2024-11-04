<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index() {
        $products = Product::orderBy('id', 'desc')->get();
        return Inertia::render('Product/Index', compact('products'));
    }

    public function filter(Request $request) {
        $query = Product::query();
        if($request->name) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        $products = $query->get();
        return response()->json($products ?? []);
    }

    public function save(Request $request) {
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price ?? 0,
            'settings' => $request->settings,
        ];
        Product::updateOrCreate(['id' => $request->id], $data);

        return back()->with('success', 'Product saved successfully');
    }

    public function delete($id) {
        $product = Product::findOrFail($id);
        $product->delete();
        return back()->with('success', 'Product deleted successfully');
    }
}
