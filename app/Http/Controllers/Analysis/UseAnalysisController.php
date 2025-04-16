<?php

namespace App\Http\Controllers\Analysis;

use App\Http\Controllers\Controller;
use App\Models\PackageUseHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class UseAnalysisController extends Controller
{
    public function index()
    {
        // // Get active users
        // $userIds = User::where('role', 'user')->where('status', true)->pluck('id');

        // // Get package use history
        // $history = PackageUseHistory::whereIn('user_id', $userIds)->get();

        // // file_put_contents(__DIR__ . '/products.json', json_encode($history, JSON_UNESCAPED_UNICODE));
        // // return 0;

        // // Deserialize `cart_contents` if serialized
        // $modifiedHistory = collect($history)->map(function ($record) {
        //     $useDetails = collect($record->use_details);
        //     $record->use_details = $useDetails->map(function ($item) {
        //         try {
        //             if (is_string($item['cart_contents']) && @unserialize($item['cart_contents']) !== false) {
        //                 $item['cart_contents'] = unserialize($item['cart_contents']);
        //             }
        //         } catch (\Throwable $th) {
        //             // silently ignore
        //         }
        //         return $item;
        //     });
        //     return $record;
        // });

        // $product_sale = [];

        // // Process the cleaned data
        // foreach ($modifiedHistory as $entry) {
        //     foreach ($entry->use_details as $item) {
        //         $contents = [];

        //         if (isset($item['cart_contents'])) {
        //             if (is_array($item['cart_contents'])) {
        //                 $contents = $item['cart_contents'];
        //             } elseif (isset($item['cart_contents']['products']) && is_array($item['cart_contents']['products'])) {
        //                 $contents = $item['cart_contents']['products'];
        //             } elseif (is_array($item['cart_contents'])) {
        //                 $contents = [$item['cart_contents']];
        //             }
        //         }

        //         foreach ($contents as $content_item) {
        //             $url = $content_item['product_url'] ?? null;
        //             if (!$url) continue;

        //             if (!isset($product_sale[$url])) {
        //                 $product_sale[$url] = [
        //                     'item' => $content_item,
        //                     'total_quantity' => 0,
        //                     'missing_count' => 0,
        //                 ];
        //             }

        //             $quantity = isset($content_item['quantity']) ? intval($content_item['quantity']) : 0;
        //             if($quantity < 500) {
        //                 $product_sale[$url]['total_quantity'] += $quantity;

        //                 if (($item['from'] ?? '') === 'missing_order') {
        //                     $product_sale[$url]['missing_count'] += 1;
        //                 }
        //             }
        //         }
        //     }
        // }

        // // Final structure
        // $final = [];

        // foreach ($product_sale as $data) {
        //     $final[] = [
        //         'name' => $data['item']['name'] ?? '',
        //         'total_order_count' => $data['total_quantity'],
        //         'missing_count' => $data['missing_count'],
        //         'real_order' => $data['total_quantity'] - $data['missing_count'],
        //         // 'product_url' => $data['item']['product_url'] ?? '',
        //     ];
        // }

        // // return $final;

        // // Save final output to JSON file
        // // return $final;
        // file_put_contents(__DIR__ . '/product_sales.json', json_encode($final, JSON_UNESCAPED_UNICODE));

        $users = User::where('role', 'user')->get();
        return Inertia::render('UserAnalysis/Index', compact('users'));
    }

    public function getUseReport(Request $request)
    {
        $query = PackageUseHistory::where('user_id', $request->user_id);
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        $history = $query->get();

        $modifiedHistory = collect($history)->map(function ($record) {
            // return $record->use_details;
            $useDetails = collect($record->use_details);
            $record->use_details = $useDetails->map(function ($item) {
                try {
                    if (is_string($item['cart_contents']) && @unserialize($item['cart_contents']) !== false) {
                        $item['cart_contents'] = unserialize($item['cart_contents']);
                    }
                } catch (\Throwable $th) {
                }
                return $item;
            });

            return $record;
        });

        return response()->json($modifiedHistory ?? []);
    }
}
