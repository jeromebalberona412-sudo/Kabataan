<?php

namespace App\Modules\Guest_Kabataan\Services;

use App\Models\Barangay;
use App\Models\ProgramSurvey;
use App\Models\ScheduleProgram;
use App\Services\BarangayLogoUrlService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GuestKabataanService
{
    public const SESSION_KEY = 'guest_kabataan';

    /**
     * @return Collection<int, Barangay>
     */
    public function barangays(): Collection
    {
        $barangays = Barangay::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'municipality', 'province']);

        $logos = app(BarangayLogoUrlService::class)->resolveMany($barangays->pluck('id')->all());
        $barangays->each(function (Barangay $barangay) use ($logos) {
            $barangay->setAttribute('logo_url', $logos[$barangay->id] ?? null);
        });

        return $barangays;
    }

    public function rememberBarangay(int $barangayId): Barangay
    {
        $barangay = Barangay::query()->findOrFail($barangayId);

        session([
            self::SESSION_KEY => [
                'barangay_id' => $barangay->id,
                'barangay_name' => $barangay->name,
                'barangay_slug' => $barangay->slug,
            ],
        ]);

        return $barangay;
    }

    public function sessionBarangay(): ?Barangay
    {
        $stored = session(self::SESSION_KEY);
        $barangayId = is_array($stored) ? ($stored['barangay_id'] ?? null) : null;
        if (! is_numeric($barangayId)) {
            return null;
        }

        return Barangay::query()->find((int) $barangayId);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Programs SK Officials has opened for this barangay.
     * Scholarship and sports schedules, opened surveys, and youth programs
     * set to Planned or Ongoing are included. Closed programs are not.
     *
     * @return list<array{name: string, action: string}>
     */
    public function openPrograms(Barangay $barangay): array
    {
        $tiles = array_merge(
            $this->openScheduleTiles($barangay),
            $this->openSurveyTiles($barangay),
            $this->scheduledYouthTiles($barangay),
        );

        $unique = [];
        foreach ($tiles as $tile) {
            $key = mb_strtolower($tile['name']);
            if (! isset($unique[$key]) || $tile['action'] === 'apply') {
                $unique[$key] = $tile;
            }
        }

        $programs = array_values($unique);
        usort($programs, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $programs;
    }

    /**
     * @return list<array{name: string, action: string}>
     */
    private function openScheduleTiles(Barangay $barangay): array
    {
        return ScheduleProgram::query()
            ->active()
            ->where('barangay_id', $barangay->id)
            ->where('status', ScheduleProgram::STATUS_OPEN)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', Carbon::today());
            })
            ->orderBy('program_letter')
            ->orderBy('program_name')
            ->get()
            ->map(fn (ScheduleProgram $program) => $this->presentProgram($program))
            ->all();
    }

    /**
     * @return list<array{name: string, action: string}>
     */
    private function openSurveyTiles(Barangay $barangay): array
    {
        $today = Carbon::today()->toDateString();

        return ProgramSurvey::query()
            ->with('abyipProgram:id,program_name,code')
            ->where('barangay_id', $barangay->id)
            ->whereDate('close_date', '>=', $today)
            ->where(function ($query) use ($today) {
                $query->whereRaw('LOWER(status) = ?', ['open'])
                    ->orWhere(function ($scheduled) use ($today) {
                        $scheduled->whereRaw('LOWER(status) = ?', ['scheduled'])
                            ->whereDate('open_date', '<=', $today);
                    });
            })
            ->orderBy('id')
            ->get()
            ->map(function (ProgramSurvey $survey) {
                $name = trim((string) ($survey->abyipProgram?->program_name ?? ''));
                $letter = strtoupper(trim((string) ($survey->abyipProgram?->code ?? '')));

                return $this->presentNamedProgram($name, $letter, '');
            })
            ->all();
    }

    /**
     * Youth programs officials marked Planned or Ongoing in Youth Programs.
     *
     * @return list<array{name: string, action: string}>
     */
    private function scheduledYouthTiles(Barangay $barangay): array
    {
        if (! Schema::hasTable('abyip_program_durations') || ! Schema::hasColumn('abyip_program_durations', 'status')) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $rows = DB::table('abyip_program_durations')
            ->join('abyip', 'abyip.id', '=', 'abyip_program_durations.abyip_program_id')
            ->where('abyip_program_durations.barangay_id', $barangay->id)
            ->whereRaw("LOWER(COALESCE(abyip_program_durations.status, '')) IN ('planned', 'ongoing')")
            ->whereDate('abyip_program_durations.end_date', '>=', $today)
            ->orderBy('abyip.code')
            ->get(['abyip.program_name', 'abyip.code']);

        return $rows
            ->map(function (object $row) {
                $name = trim((string) ($row->program_name ?? ''));
                $letter = strtoupper(trim((string) ($row->code ?? '')));

                return $this->presentNamedProgram($name, $letter, '');
            })
            ->all();
    }

    /**
     * @return array{name: string, action: string}
     */
    private function presentProgram(ScheduleProgram $program): array
    {
        $letter = strtoupper(trim((string) ($program->program_letter ?? '')));
        $name = trim((string) $program->program_name);
        $type = trim((string) ($program->program_type ?? ''));

        return $this->presentNamedProgram($name, $letter, $type);
    }

    /**
     * @return array{name: string, action: string}
     */
    private function presentNamedProgram(string $name, string $letter, string $type): array
    {
        $title = $name !== '' ? $name : 'Untitled program';

        return [
            'name' => $title,
            'action' => $this->usesApplication($letter, $type, $title) ? 'apply' : 'survey',
        ];
    }

    /**
     * Scholarship (A) and sports (I) use an application. Other open programs use a survey.
     */
    private function usesApplication(string $letter, string $type, string $name): bool
    {
        if (in_array($letter, ['A', 'I'], true)) {
            return true;
        }

        $haystack = strtolower($type.' '.$name);

        return str_contains($haystack, 'scholarship') || str_contains($haystack, 'sports');
    }
}
