<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $suppliers = Supplier::all();
        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150|unique:suppliers',
            'code' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create($request->only(['name', 'code', 'notes']));

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:150|unique:suppliers,name,' . $supplier->id,
            'code' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $supplier->update($request->only(['name', 'code', 'notes', 'active']));

        return response()->json($supplier);
    }
}
