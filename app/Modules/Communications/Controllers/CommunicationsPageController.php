<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Services\ConversationService;
use App\Modules\Profile\Services\ProfileImageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunicationsPageController extends Controller
{
    public function index(Request $request, ConversationService $conversations): View
    {
        $conversationId = $request->integer('conversation') ?: null;
        $user = Auth::user();
        $barangayOfficials = [];
        if ($user) {
            try {
                $barangayOfficials = $conversations->searchUsers($user, '')->values()->all();
            } catch (\Throwable) {
                $barangayOfficials = [];
            }
        }

        return view('communications::index', [
            'initialConversationId' => $conversationId,
            'currentUserId' => (int) Auth::id(),
            'portalUserType' => config('communications.portal_user_type', 'kabataan'),
            'currentUserAvatar' => $user
                ? app(ProfileImageService::class)->resolveDisplayUrl($user)
                : null,
            'barangayOfficials' => $barangayOfficials,
        ]);
    }

    public function callHistory(): View
    {
        return view('communications::call-history');
    }

    public function unreadCount(ConversationService $conversations)
    {
        $user = Auth::user();
        $count = $user ? $conversations->unreadTotalForUser($user) : 0;

        return response()->json(['unread_count' => $count]);
    }
}
