<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('family')) {
            $query->where('family', $request->get('family'));
        }

        if ($request->has('brand')) {
            $query->where('brand', $request->get('brand'));
        }

        $products = $query->latest()->paginate(50);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }

    public function auditLog(Product $product): JsonResponse
    {
        $logs = $product->auditLogs()
            ->with('import')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }
}
