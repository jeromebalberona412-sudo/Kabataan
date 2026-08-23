<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Services\KabataanNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private readonly KabataanNotificationService $notificationService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $notifications = $this->notificationService->allForUser($user);
        $unreadCount = $this->notificationService->unreadCountForUser($user);
        $barangayName = 'Your Barangay';

        if ($user) {
            $registration = KabataanRegistration::with('barangay')
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            $barangayName = $registration?->barangay?->name
                ?? ($user->barangay_id ? Barangay::find($user->barangay_id)?->name : null)
                ?? $barangayName;
        }

        return view('notifications::notifications', compact('notifications', 'unreadCount', 'barangayName'));
    }

    public function list(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'data' => $this->notificationService->allForUser($user),
            'unread_count' => $this->notificationService->unreadCountForUser($user),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->notificationService->markRead($user, $id);

        return response()->json([
            'success' => true,
            'unread_count' => $this->notificationService->unreadCountForUser($user),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->notificationService->markAllRead($user);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}
