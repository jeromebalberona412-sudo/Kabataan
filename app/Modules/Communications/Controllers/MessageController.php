<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\Message;
use App\Modules\Communications\Services\FaqAutomationResponder;
use App\Modules\Communications\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messages,
        protected FaqAutomationResponder $faqResponder
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $beforeId = $request->integer('before_id') ?: null;
        $items = $this->messages->paginate($conversation, Auth::user(), $beforeId ?: null);

        return response()->json([
            'messages' => $items,
            'reaction_emojis' => $this->messages->allowedReactions(),
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('send', $conversation);

        $max = (int) config('communications.message_max_length', 1000);
        $file = $this->uploadedAttachment($request);
        $hasFile = $file instanceof UploadedFile;

        $validated = $request->validate([
            'body' => [$hasFile ? 'nullable' : 'required', 'string', 'max:'.$max],
            'attachment' => ['nullable', 'file'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file'],
            'faq_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $body = (string) ($validated['body'] ?? '');

        $user = Auth::user();
        $message = $file
            ? $this->messages->sendWithAttachment($conversation, $user, $body, $file)
            : $this->messages->send($conversation, $user, $body);

        $payload = [
            'message' => $this->messages->serialize($message, $user),
        ];

        if (! $file) {
            $faqId = isset($validated['faq_id']) ? (int) $validated['faq_id'] : null;
            $automated = $this->faqResponder->maybeRespond($conversation, $user, $message, $faqId);
            if ($automated !== null) {
                $payload['automated_message'] = $this->messages->serialize($automated, $user);
            }
        }

        return response()->json($payload, 201);
    }

    public function toggleReaction(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $payload = $this->messages->toggleReaction($message, Auth::user(), $validated['emoji']);

        return response()->json($payload);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('send', $conversation);

        $max = (int) config('communications.message_max_length', 1000);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.$max],
        ]);

        $updated = $this->messages->edit($message, Auth::user(), $validated['body']);

        return response()->json([
            'message' => $this->messages->serialize($updated, Auth::user()),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('view', $conversation);

        $request->merge([
            'scope' => $request->input('scope') ?? $request->query('scope'),
        ]);

        $validated = $request->validate([
            'scope' => ['required', 'in:me,all'],
        ]);

        if ($validated['scope'] === 'all') {
            $updated = $this->messages->deleteForAll($message, Auth::user());

            return response()->json([
                'scope' => 'all',
                'message' => $this->messages->serialize($updated, Auth::user()),
            ]);
        }

        $this->messages->deleteForMe($message, Auth::user());

        return response()->json([
            'scope' => 'me',
            'message_id' => (int) $message->id,
        ]);
    }

    protected function uploadedAttachment(Request $request): ?UploadedFile
    {
        $file = $request->file('attachment');
        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (! $request->hasFile('attachments')) {
            return null;
        }

        $uploaded = $request->file('attachments');
        if ($uploaded instanceof UploadedFile) {
            return $uploaded;
        }

        if (is_array($uploaded)) {
            foreach ($uploaded as $item) {
                if ($item instanceof UploadedFile) {
                    return $item;
                }
            }
        }

        return null;
    }
}
