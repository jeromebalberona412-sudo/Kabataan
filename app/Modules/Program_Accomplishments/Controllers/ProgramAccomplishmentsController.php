<?php

namespace App\Modules\Program_Accomplishments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Services\BarangayLogoUrlService;
use App\Services\PublicBarangayAccomplishmentsService;
use App\Services\PublicProgramAccomplishmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProgramAccomplishmentsController extends Controller
{
    public function __construct(
        private readonly PublicBarangayAccomplishmentsService $accomplishmentsService,
        private readonly PublicProgramAccomplishmentService $programAccomplishmentService,
        private readonly BarangayLogoUrlService $barangayLogoUrlService,
    ) {}

    public function index(): View
    {
        $publishedBarangayIds = [];

        try {
            $publishedBarangayIds = $this->programAccomplishmentService->barangayIdsWithPublishedReports();
        } catch (Throwable $e) {
            Log::warning('Program accomplishment reports unavailable: '.$e->getMessage());
        }

        try {
            $barangays = Barangay::query()
                ->withExists([
                    'accomplishmentDocuments as accomplishments_exists',
                ])
                ->orderBy('name')
                ->get();
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable, falling back to basic barangay list: '.$e->getMessage());

            $barangays = Barangay::query()
                ->orderBy('name')
                ->get()
                ->each(fn ($b) => $b->setAttribute('accomplishments_exists', false));
        }

        $barangays->each(function ($barangay) use ($publishedBarangayIds) {
            $hasProgramReports = in_array((int) $barangay->id, $publishedBarangayIds, true);
            $barangay->setAttribute('program_reports_exists', $hasProgramReports);
            $barangay->setAttribute('accomplishments_exists', (bool) $barangay->accomplishments_exists || $hasProgramReports);
            try {
                $barangay->setAttribute('logo_url', $this->barangayLogoUrlService->resolve((int) $barangay->id));
            } catch (Throwable $e) {
                $barangay->setAttribute('logo_url', null);
            }
        });

        return view('program_accomplishments::barangays.index', [
            'barangays' => $barangays,
            'hideFooter' => true,
        ]);
    }

    public function show(Barangay $barangay): View
    {
        try {
            $accomplishment = $this->accomplishmentsService->latestForBarangay($barangay);
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable for barangay '.$barangay->id.': '.$e->getMessage());
            $accomplishment = null;
        }

        $programReports = collect();
        try {
            $programReports = $this->programAccomplishmentService->publishedForBarangay((int) $barangay->id);
        } catch (Throwable $e) {
            Log::warning('Program accomplishment reports unavailable for barangay '.$barangay->id.': '.$e->getMessage());
        }

        $logoUrl = null;
        try {
            $logoUrl = $this->barangayLogoUrlService->resolve((int) $barangay->id);
        } catch (Throwable $e) {
            $logoUrl = null;
        }

        $programCards = $programReports->map(
            fn ($report) => $this->programAccomplishmentService->toPublicCard(
                $report,
                (string) $barangay->name,
                (string) $barangay->slug
            )
        )->values();

        $categoryCounts = $programCards
            ->groupBy('category')
            ->map->count()
            ->sortKeys();

        $years = $programCards->pluck('year')->unique()->sortDesc()->values();

        $latestDate = $programReports
            ->map(fn ($report) => $report->published_at ?: $report->actual_completion_date ?: $report->updated_at)
            ->filter()
            ->sortDesc()
            ->first();

        $stats = [
            'total' => $programCards->count(),
            'beneficiaries' => $programCards->sum('beneficiaries'),
            'expenditure' => $programCards->sum('expenditure'),
            'latest' => $latestDate?->format('F j, Y'),
        ];

        return view('program_accomplishments::barangays.show', [
            'barangay' => $barangay,
            'accomplishment' => $accomplishment,
            'programReports' => $programReports,
            'programCards' => $programCards,
            'categoryCounts' => $categoryCounts,
            'years' => $years,
            'stats' => $stats,
            'logoUrl' => $logoUrl,
            'hideFooter' => true,
        ]);
    }

    public function report(Barangay $barangay, int $report): RedirectResponse
    {
        return redirect()->route('program_accomplishments.barangays.show', $barangay);
    }

    public function downloadDocument(Barangay $barangay, int $document): BinaryFileResponse|StreamedResponse
    {
        $response = $this->programAccomplishmentService->downloadPublicDocument((int) $barangay->id, $document);

        abort_unless($response !== null, 404);

        return $response;
    }
}
