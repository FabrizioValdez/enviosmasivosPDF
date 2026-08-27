<?php

namespace App\Jobs;

use App\Models\ProductImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $delay = 3600;

    /** @var ProductImport */
    public $import;

    public function __construct(ProductImport $import)
    {
        $this->import = $import;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $filePath = 'imports/' . $this->import->filename;

        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);

            Log::info("Cleaned up import file", [
                'import_id' => $this->import->id,
                'filename' => $this->import->filename,
            ]);
        }
    }
}
