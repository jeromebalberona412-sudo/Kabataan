<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\BarangayAbyip;
use App\Services\AbyipPdfExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AbyipUploadController extends Controller
{
    public function create(): View
    {
        $barangays = Barangay::query()->orderBy('name')->get(['id', 'name', 'slug']);

        return view('admin.abyip-upload', compact('barangays'));
    }

    public function store(Request $request, AbyipPdfExtractor $extractor): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $barangay = Barangay::query()->findOrFail($validated['barangay_id']);
        $year = (int) $validated['year'];
        $file = $request->file('pdf');
        $storagePath = $file->storeAs(
            "abyip-pdfs/{$barangay->slug}",
            "{$year}.pdf"
        );
        $absolutePath = storage_path('app/'.$storagePath);
        $data = $extractor->extract($absolutePath);

        if (($data['rows'] ?? []) === [] && ($data['estimated_budget'] ?? 0) <= 0) {
            return back()
                ->withInput()
                ->withErrors(['pdf' => 'The PDF could not be parsed. Please verify the file and try again.']);
        }

        DB::transaction(function () use ($barangay, $data, $year, $storagePath) {
            $abyip = BarangayAbyip::query()->updateOrCreate(
                ['barangay_id' => $barangay->id, 'year' => $year],
                [
                    'estimated_budget' => $data['estimated_budget'] ?? 0,
                    'sk_fund' => $data['sk_fund'] ?? 0,
                    'total_expenditure' => $data['total_expenditure'] ?? ($data['sk_fund'] ?? 0),
                    'chairperson_name' => $data['chairperson_name'] ?? null,
                    'approved_by_name' => $data['approved_by_name'] ?? null,
                    'source_pdf_path' => $storagePath,
                    'extracted_at' => now(),
                ]
            );

            $abyip->items()->delete();

            foreach ($data['rows'] ?? [] as $index => $row) {
                $abyip->items()->create(array_merge($row, ['sort_order' => $index]));
            }
        });

        return redirect()
            ->route('admin.abyip.upload.create')
            ->with('status', "ABYIP uploaded for {$barangay->name} ({$year}).");
    }
}
