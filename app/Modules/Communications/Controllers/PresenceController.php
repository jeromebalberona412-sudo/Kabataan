<?php

namespace App\Modules\Communications\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $validated = $request->validate([
            'online' => ['sometimes', 'boolean'],
        ]);

        $goingOnline = ! array_key_exists('online', $validated) || (bool) $validated['online'];

        $updates = [];
        // Only bump last_seen when the user is online. Refreshing last_seen on
        // pagehide/offline kept peers green for up to 2 minutes after leave.
        if ($goingOnline && Schema::hasColumn('users', 'last_seen')) {
            $updates['last_seen'] = now();
        }
        if (Schema::hasColumn('users', 'online_status')) {
            $updates['online_status'] = $goingOnline ? 'online' : 'offline';
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        return response()->json([
            'ok' => true,
            'online' => $goingOnline,
            'last_seen' => $goingOnline ? now()->toIso8601String() : null,
        ]);
    }
}
