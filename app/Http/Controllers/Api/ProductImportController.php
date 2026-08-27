<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImportJob;
use App\Models\ProductImport;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    public function index(): JsonResponse
    {
        $imports = ProductImport::with('supplier')
            ->latest()
            ->paginate(20);

        return response()->json($imports);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $file = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $fileHash = hash_file('sha256', $file->getRealPath());

        $existingImport = ProductImport::where('file_hash', $fileHash)->first();

        if ($existingImport) {
            return response()->json([
                'message' => 'This file has already been imported',
                'import_id' => $existingImport->id,
                'status' => $existingImport->status,
            ], 409);
        }

        $file->storeAs('imports', $filename, 'public');

        $import = ProductImport::create([
            'supplier_id' => $request->supplier_id,
            'filename' => $filename,
            'file_hash' => $fileHash,
            'status' => ProductImport::STATUS_PENDING,
        ]);

        ProcessProductImportJob::dispatch($import);

        return response()->json([
            'message' => 'Import started successfully',
            'import' => $import,
        ], 201);
    }

    public function show(ProductImport $import): JsonResponse
    {
        $import->load(['supplier', 'items.product']);

        return response()->json($import);
    }

    public function items(ProductImport $import, Request $request): JsonResponse
    {
        $status = $request->get('status');

        $query = $import->items()->with('product');

        if ($status) {
            $query->where('status', $status);
        }

        $items = $query->latest()->paginate(50);

        return response()->json($items);
    }

    public function confirm(ProductImport $import): JsonResponse
    {
        if ($import->status !== ProductImport::STATUS_REQUIRES_REVIEW) {
            return response()->json([
                'message' => 'This import cannot be confirmed',
            ], 400);
        }

        $import->update(['status' => ProductImport::STATUS_COMPLETED]);

        return response()->json([
            'message' => 'Import confirmed successfully',
        ]);
    }

    public function cancel(ProductImport $import): JsonResponse
    {
        if (in_array($import->status, [
            ProductImport::STATUS_COMPLETED,
            ProductImport::STATUS_FAILED,
        ])) {
            return response()->json([
                'message' => 'This import cannot be cancelled',
            ], 400);
        }

        $import->update(['status' => ProductImport::STATUS_FAILED]);

        return response()->json([
            'message' => 'Import cancelled successfully',
        ]);
    }

    public function destroy(ProductImport $import): JsonResponse
    {
        $filePath = 'imports/' . $import->filename;

        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $import->delete();

        return response()->json([
            'message' => 'Import deleted successfully',
        ]);
    }
}
