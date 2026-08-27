<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Services\ProductImport\ProductImportOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;
    public $backoff = 60;

    /** @var ProductImport */
    public $import;

    public function __construct(ProductImport $import)
    {
        $this->import = $import;
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        Log::info("Starting import processing", [
            'import_id' => $this->import->id,
            'filename' => $this->import->filename,
        ]);

        $orchestrator = new ProductImportOrchestrator();
        $orchestrator->processImport($this->import);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Import job failed", [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->markAsFailed($exception->getMessage());
    }
}
