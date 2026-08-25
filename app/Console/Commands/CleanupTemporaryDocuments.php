<?php

namespace App\Console\Commands;

use App\Models\SupportingDocumentVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupTemporaryDocuments extends Command
{
    protected $signature = 'documents:cleanup';

    protected $description = 'Delete expired temporary supporting-document files per retention policy';

    public function handle(): int
    {
        if (! Schema::hasTable('supporting_document_verifications')) {
            $this->info('supporting_document_verifications table not found. Nothing to clean.');

            return self::SUCCESS;
        }

        $disk = (string) config('documents.temp_disk', 'local');
        $deleted = 0;

        SupportingDocumentVerification::query()
            ->whereNotNull('temp_storage_path')
            ->where(function ($query) {
                $query->whereNull('retain_until')
                    ->orWhere('retain_until', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($disk, &$deleted) {
                foreach ($rows as $row) {
                    $path = $row->temp_storage_path;

                    try {
                        if ($path && Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            $deleted++;
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('documents:cleanup failed for path', [
                            'verification_id' => $row->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }

                    $row->forceFill(['temp_storage_path' => null])->save();
                }
            });

        $this->info("Removed {$deleted} temporary document file(s).");

        return self::SUCCESS;
    }
}
