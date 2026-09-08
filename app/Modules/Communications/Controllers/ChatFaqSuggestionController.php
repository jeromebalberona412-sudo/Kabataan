<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\ConversationParticipant;
use App\Modules\Communications\Services\ChatAutomationService;
use App\Modules\Communications\Services\ConversationService;
use App\Modules\Communications\Services\ParticipantTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ChatFaqSuggestionController extends Controller
{
    public function __construct(
        protected ChatAutomationService $automations,
        protected ConversationService $conversations,
        protected ParticipantTypeResolver $types
    ) {}

    public function __invoke(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = Auth::user();
        if ($this->types->portalType() !== ParticipantTypeResolver::KABATAAN) {
            return response()->json(['faqs' => [], 'enabled' => false, 'sk_official_id' => null]);
        }

        if (! $this->conversations->isParticipant($conversation, $user)) {
            abort(403);
        }

        $officialId = $this->resolveOfficialId($conversation);
        if ($officialId === null) {
            return response()->json(['faqs' => [], 'enabled' => false, 'sk_official_id' => null]);
        }

        // Per SK Official: only that official's enabled FAQs appear for this chat.
        $faqs = $this->automations->activeSuggestionsForOfficial($officialId);

        return response()->json([
            'enabled' => $faqs !== [],
            'faqs' => $faqs,
            'sk_official_id' => $officialId,
        ]);
    }

    protected function resolveOfficialId(Conversation $conversation): ?int
    {
        $conversation->loadMissing('participants');

        /** @var ConversationParticipant|null $official */
        $official = $conversation->participants->first(
            fn (ConversationParticipant $p) => $p->user_type === ParticipantTypeResolver::SK_OFFICIAL
        );

        if ($official !== null) {
            return (int) $official->user_id;
        }

        return null;
    }
}
