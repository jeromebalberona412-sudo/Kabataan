<?php

namespace App\Modules\Baranggay_ABYIP\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Modules\Baranggay_ABYIP\Services\Baranggay_ABYIPService;
use App\Services\BarangayLogoUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Baranggay_ABYIPController extends Controller
{
    public function __construct(
        private readonly Baranggay_ABYIPService $abyipService,
        private readonly BarangayLogoUrlService $barangayLogoUrlService,
    ) {}

    public function index(): View
    {
        $barangayIdsWithPdf = [];

        try {
            $barangayIdsWithPdf = $this->abyipService->barangayIdsWithPublicPdf();
        } catch (Throwable $e) {
            Log::warning('Public barangay ABYIP list unavailable: '.$e->getMessage());
        }

        try {
            $barangays = Barangay::query()->orderBy('name')->get();
        } catch (QueryException $e) {
            Log::warning('Barangay list unavailable for ABYIP page: '.$e->getMessage());
            $barangays = collect();
        }

        $barangays->each(function ($barangay) use ($barangayIdsWithPdf) {
            $barangay->setAttribute('abyip_exists', in_array((int) $barangay->id, $barangayIdsWithPdf, true));
            try {
                $barangay->setAttribute('logo_url', $this->barangayLogoUrlService->resolve((int) $barangay->id));
            } catch (Throwable $e) {
                $barangay->setAttribute('logo_url', null);
            }
        });

        return view('baranggay_abyip::baranggays.index', [
            'barangays' => $barangays,
            'hideFooter' => true,
        ]);
    }

    public function show(Barangay $barangay): View
    {
        $logoUrl = null;
        try {
            $logoUrl = $this->barangayLogoUrlService->resolve((int) $barangay->id);
        } catch (Throwable $e) {
            $logoUrl = null;
        }

        return view('baranggay_abyip::baranggays.show', [
            'barangay' => $barangay,
            'logoUrl' => $logoUrl,
            'documentsUrl' => route('baranggay_abyip.documents', $barangay->slug),
            'hideFooter' => true,
        ]);
    }

    public function documents(Barangay $barangay): JsonResponse
    {
        try {
            $documents = $this->abyipService->publicDocumentsForBarangay($barangay);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch public ABYIP documents for barangay '.$barangay->id.': '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to load ABYIP documents right now.',
                'data' => [],
            ], 500);
        }

        return response()->json([
            'data' => $documents->values(),
        ]);
    }

    public function file(Barangay $barangay, int $document): Response
    {
        $pdf = $this->abyipService->pdfBinary($barangay, $document);

        abort_if($pdf === null, 404);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function legacyFile(Barangay $barangay, int $legacy): Response
    {
        $pdf = $this->abyipService->legacyPdfBinary($barangay, $legacy);

        abort_if($pdf === null, 404);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
