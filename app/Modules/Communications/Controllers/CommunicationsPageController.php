<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunicationsPageController extends Controller
{
    public function index(Request $request): View
    {
        $conversationId = $request->integer('conversation') ?: null;

        return view('communications::index', [
            'initialConversationId' => $conversationId,
            'currentUserId' => (int) Auth::id(),
            'portalUserType' => config('communications.portal_user_type', 'kabataan'),
            'currentUserAvatar' => Auth::user()
                ? app(\App\Modules\Profile\Services\ProfileImageService::class)->resolveDisplayUrl(Auth::user())
                : null,
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
