<?php

namespace App\Modules\Guest_Kabataan\Services;

use App\Models\Barangay;
use App\Models\ProgramApplication;
use App\Models\ScheduleProgram;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GuestKabataanService
{
    public const SESSION_KEY = 'guest_kabataan';

    /** @var array<string, string> */
    private const LETTER_LABELS = [
        'A' => 'Education',
        'B' => 'Environment',
        'C' => 'Disaster Preparedness',
        'D' => 'Livelihood',
        'E' => 'Health',
        'F' => 'Anti-Drugs',
        'G' => 'Gender and Development',
        'H' => 'Feeding',
        'I' => 'Sports Development',
        'J' => 'Others',
    ];

    /**
     * @return Collection<int, Barangay>
     */
    public function barangays(): Collection
    {
        return Barangay::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'municipality', 'province']);
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
     * Open programs a guest may read for the selected barangay.
     *
     * @return list<array<string, mixed>>
     */
    public function openPrograms(Barangay $barangay): array
    {
        $programs = ScheduleProgram::query()
            ->active()
            ->where('barangay_id', $barangay->id)
            ->where('status', ScheduleProgram::STATUS_OPEN)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', Carbon::today());
            })
            ->orderBy('program_letter')
            ->orderBy('program_name')
            ->orderByDesc('start_date')
            ->get();

        if ($programs->isEmpty()) {
            return [];
        }

        $usedSlots = ProgramApplication::query()
            ->selectRaw('program_id, COUNT(*) as used_count')
            ->whereIn('program_id', $programs->pluck('id'))
            ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
            ->groupBy('program_id')
            ->pluck('used_count', 'program_id');

        return $programs
            ->map(function (ScheduleProgram $program) use ($usedSlots) {
                return $this->presentProgram($program, (int) ($usedSlots[$program->id] ?? 0));
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProgram(ScheduleProgram $program, int $usedSlots): array
    {
        $letter = strtoupper(trim((string) ($program->program_letter ?? '')));
        $sports = is_array($program->sports_details) ? $program->sports_details : [];
        $sportLabel = trim((string) ($sports['sport_label'] ?? $sports['sport_name'] ?? ''));
        $slots = null;
        if ($program->participation_quantity !== null) {
            $slots = max(0, (int) $program->participation_quantity - $usedSlots);
        }

        return [
            'id' => $program->id,
            'name' => trim((string) $program->program_name) ?: 'Untitled program',
            'type' => trim((string) ($program->program_type ?? '')),
            'letter' => $letter,
            'category' => self::LETTER_LABELS[$letter] ?? ($letter !== '' ? $letter : 'Program'),
            'committee' => trim((string) ($program->committee ?? '')),
            'sport' => $sportLabel,
            'start' => $program->start_date?->format('M j, Y') ?? '',
            'end' => $program->end_date?->format('M j, Y') ?? '',
            'announcement' => trim(strip_tags((string) ($program->announcement ?? ''))),
            'slots' => $slots,
            'capacity' => $program->participation_quantity,
        ];
    }
}
