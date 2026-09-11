<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkOfficialsNotificationDispatcher
{
    /**
     * @var array<string, string>
     */
    private const LETTER_COMMITTEE = [
        'B' => 'environmental',
        'C' => 'disaster',
        'D' => 'livelihood',
        'E' => 'medicines',
        'F' => 'antidrug',
        'G' => 'gender',
        'H' => 'feeding',
        'J' => 'others',
    ];

    public function notifyKkProfilingSubmission(int $barangayId, string $fullName): void
    {
        if (! Schema::hasTable('sk_officials_notifications')) {
            return;
        }

        $this->insertForBarangayOfficials(
            $barangayId,
            'kk_profiling',
            'New KK Profiling Request',
            "{$fullName} submitted a KK Profiling registration that needs your review.",
            '/kk-profiling-requests',
        );
    }

    public function notifySurveyResponse(
        int $barangayId,
        string $respondentName,
        string $programName,
        ?string $programLetter,
    ): void {
        if (! Schema::hasTable('sk_officials_notifications')) {
            return;
        }

        $letter = strtoupper(trim((string) $programLetter));
        $committee = self::LETTER_COMMITTEE[$letter] ?? null;
        $actionUrl = $committee ? "/{$committee}-survey-results" : '/schedule-programs';

        $this->insertForBarangayOfficials(
            $barangayId,
            'survey',
            'New Survey Response',
            "{$respondentName} submitted a response for {$programName}.",
            $actionUrl,
        );
    }

    public function notifyProgramApplication(
        int $barangayId,
        string $applicantName,
        string $programName,
        ?string $programLetter,
    ): void {
        if (! Schema::hasTable('sk_officials_notifications')) {
            return;
        }

        $letter = strtoupper(trim((string) $programLetter));
        $actionUrl = $letter === 'I' ? '/sports-requests' : '/scholarship-applications';
        $title = match ($letter) {
            'I' => 'New Sports Application',
            'A' => 'New Scholarship Application',
            default => 'New '.$programName.' Application',
        };
        $body = match ($letter) {
            'I' => "{$applicantName} submitted a sports application for {$programName}.",
            'A' => "{$applicantName} submitted a scholarship application for {$programName}.",
            default => "{$applicantName} submitted an application for {$programName}.",
        };

        $this->insertForBarangayOfficials(
            $barangayId,
            'program',
            $title,
            $body,
            $actionUrl,
        );
    }

    /**
     * Notify the post owner (SK Official). One unread notification per post —
     * later activity updates the same row.
     */
    public function notifyCommunityFeedPostActivity(
        int $ownerUserId,
        int $postId,
        string $title,
        string $body,
    ): void {
        if (! Schema::hasTable('sk_officials_notifications') || $ownerUserId <= 0 || $postId <= 0) {
            return;
        }

        $owner = DB::table('users')
            ->where('id', $ownerUserId)
            ->where('role', 'sk_official')
            ->where('status', 'ACTIVE')
            ->first();

        if (! $owner) {
            return;
        }

        $actionUrl = '/community-feed/comments/'.$postId;
        $now = now();

        $existingId = DB::table('sk_officials_notifications')
            ->where('user_id', $ownerUserId)
            ->where('category', 'announcement')
            ->where('action_url', $actionUrl)
            ->whereNull('read_at')
            ->orderByDesc('id')
            ->value('id');

        if ($existingId) {
            DB::table('sk_officials_notifications')
                ->where('id', $existingId)
                ->update([
                    'title' => $title,
                    'body' => $body,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('sk_officials_notifications')->insert([
            'user_id' => $ownerUserId,
            'category' => 'announcement',
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertForBarangayOfficials(
        int $barangayId,
        string $category,
        string $title,
        string $body,
        string $actionUrl,
    ): void {
        if ($barangayId <= 0) {
            return;
        }

        $officialIds = DB::table('users')
            ->where('barangay_id', $barangayId)
            ->where('role', 'sk_official')
            ->where('status', 'ACTIVE')
            ->pluck('id');

        if ($officialIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $officialIds->map(fn ($userId) => [
            'user_id' => $userId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('sk_officials_notifications')->insert($rows);
    }
}
