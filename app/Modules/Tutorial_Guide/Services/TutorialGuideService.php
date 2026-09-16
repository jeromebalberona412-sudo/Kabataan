<?php

namespace App\Modules\Tutorial_Guide\Services;

use App\Models\User;
use App\Modules\Tutorial_Guide\Models\KabataanTutorial;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TutorialGuideService
{
    public function getOrCreateTutorial(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        /** @var KabataanTutorial $record */
        $record = KabataanTutorial::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'tutorial_key' => $tutorialKey,
            ],
            [
                'current_step' => 1,
                'status' => KabataanTutorial::STATUS_NOT_STARTED,
            ]
        );

        return $record;
    }

    public function shouldAutoStart(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): bool
    {
        $record = KabataanTutorial::query()
            ->where('user_id', $user->id)
            ->where('tutorial_key', $tutorialKey)
            ->first();

        if (! $record) {
            return true;
        }

        return $record->shouldAutoStart();
    }

    public function startTutorial(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            if ($tutorial->status !== KabataanTutorial::STATUS_COMPLETED) {
                $tutorial->update([
                    'status' => KabataanTutorial::STATUS_IN_PROGRESS,
                    'started_at' => $tutorial->started_at ?? now(),
                    'last_seen_at' => now(),
                ]);
            }

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to start Kabataan tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
                'tutorial_key' => $tutorialKey,
            ]);

            throw $e;
        }
    }

    public function updateProgress(User $user, int $step, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);
            $step = max(1, $step);

            $tutorial->update([
                'current_step' => $step,
                'status' => $tutorial->status === KabataanTutorial::STATUS_COMPLETED
                    ? KabataanTutorial::STATUS_COMPLETED
                    : KabataanTutorial::STATUS_IN_PROGRESS,
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to update Kabataan tutorial progress: '.$e->getMessage(), [
                'user_id' => $user->id,
                'step' => $step,
            ]);

            throw $e;
        }
    }

    public function completeTutorial(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            $tutorial->update([
                'status' => KabataanTutorial::STATUS_COMPLETED,
                'completed_at' => now(),
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to complete Kabataan tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    public function skipTutorial(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            if (! $tutorial->hasFinishedMandatoryOnce()) {
                throw ValidationException::withMessages([
                    'tutorial' => 'Please finish the tutorial. Skip is available after your first completion.',
                ]);
            }

            $tutorial->update([
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to skip Kabataan tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    public function resetTutorial(User $user, string $tutorialKey = KabataanTutorial::DEFAULT_TUTORIAL_KEY): KabataanTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            $updates = [
                'current_step' => 1,
                'last_seen_at' => now(),
            ];

            if (! $tutorial->hasFinishedMandatoryOnce()) {
                $updates['status'] = KabataanTutorial::STATUS_IN_PROGRESS;
                $updates['started_at'] = $tutorial->started_at ?? now();
            }

            $tutorial->update($updates);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to reset Kabataan tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTutorialPayload(KabataanTutorial $tutorial): array
    {
        $finishedOnce = $tutorial->hasFinishedMandatoryOnce();

        return [
            'tutorial_key' => $tutorial->tutorial_key,
            'current_step' => (int) $tutorial->current_step,
            'status' => $tutorial->status,
            'should_auto_start' => $tutorial->shouldAutoStart(),
            'is_completed' => $tutorial->isCompleted(),
            'is_skipped' => $tutorial->isSkipped(),
            'can_skip' => $finishedOnce,
            'is_mandatory' => ! $finishedOnce,
        ];
    }
}
