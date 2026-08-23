<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\BarangayAbyip;
use App\Services\AbyipPdfExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAbyipPdf extends Command
{
    protected $signature = 'abyip:import {barangay_slug} {pdf_path} {--year=}';

    protected $description = 'Extract an ABYIP PDF and store it against a barangay';

    public function handle(AbyipPdfExtractor $extractor): int
    {
        $barangay = Barangay::query()->where('slug', $this->argument('barangay_slug'))->first();

        if ($barangay === null) {
            $this->error('Barangay not found.');

            return self::FAILURE;
        }

        $pdfPath = $this->argument('pdf_path');

        if (! is_file($pdfPath)) {
            $this->error('PDF file not found.');

            return self::FAILURE;
        }

        $data = $extractor->extract($pdfPath);
        $year = (int) ($this->option('year') ?? $data['year'] ?? now()->year);

        if (($data['rows'] ?? []) === [] && ($data['estimated_budget'] ?? 0) <= 0) {
            $this->warn('PDF parsed with no usable data. File was not imported.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($barangay, $data, $year, $pdfPath) {
            $abyip = BarangayAbyip::query()->updateOrCreate(
                ['barangay_id' => $barangay->id, 'year' => $year],
                [
                    'estimated_budget' => $data['estimated_budget'] ?? 0,
                    'sk_fund' => $data['sk_fund'] ?? 0,
                    'total_expenditure' => $data['total_expenditure'] ?? ($data['sk_fund'] ?? 0),
                    'chairperson_name' => $data['chairperson_name'] ?? null,
                    'approved_by_name' => $data['approved_by_name'] ?? null,
                    'source_pdf_path' => $pdfPath,
                    'extracted_at' => now(),
                ]
            );

            $abyip->items()->delete();

            foreach ($data['rows'] ?? [] as $index => $row) {
                $abyip->items()->create(array_merge($row, ['sort_order' => $index]));
            }
        });

        $this->info("ABYIP imported for {$barangay->name}, year {$year}.");

        return self::SUCCESS;
    }
}
