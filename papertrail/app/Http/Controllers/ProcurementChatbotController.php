<?php

namespace App\Http\Controllers;

use App\Models\ChatbotConversation;
use App\Services\AuditLogger;
use App\Services\ProcurementChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementChatbotController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $this->logOpened($request);

        return view('assistant.index', [
            'conversations' => $this->sidebarConversations($user->id),
            'activeConversation' => null,
            'messages' => collect(),
            'starterQuestions' => $this->starterQuestions(),
        ]);
    }

    public function send(Request $request, ProcurementChatbotService $chatbot): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'conversation_id' => ['nullable', 'integer', 'exists:chatbot_conversations,id'],
        ]);

        $conversation = null;

        if (! empty($validated['conversation_id'])) {
            $conversation = $this->conversationQuery($request->user()->id)
                ->findOrFail($validated['conversation_id']);
        }

        $result = $chatbot->sendMessage($request->user(), $validated['message'], $conversation);
        $conversation = $result['conversation'];
        $assistantMessage = $result['assistant_message'];

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $result['ok'],
                'conversation_id' => $conversation?->id,
                'history_url' => $conversation ? route('assistant.history', $conversation) : route('assistant.index'),
                'type' => $result['type'] ?? 'text',
                'response' => $result['response'],
                'ai_message' => $result['ai_message'] ?? $result['response'],
                'tracking_data' => $result['tracking_data'] ?? null,
                'assistant_message' => $assistantMessage ? [
                    'id' => $assistantMessage->id,
                    'role' => $assistantMessage->role,
                    'message' => $assistantMessage->message,
                    'response_type' => $assistantMessage->response_type ?? 'text',
                    'payload' => $assistantMessage->payload ?? null,
                    'created_at' => $assistantMessage->created_at?->format('M d, Y h:i A'),
                ] : null,
            ], $result['ok'] ? 200 : 503);
        }

        $route = $conversation ? route('assistant.history', $conversation) : route('assistant.index');

        return redirect($route)->with(
            $result['ok'] ? 'status' : 'error',
            $result['ok'] ? 'AI Assistant replied.' : $result['response']
        );
    }

    public function history(Request $request, ChatbotConversation $conversation): View
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 403);

        $conversation->load('messages');
        $this->logOpened($request, $conversation);

        return view('assistant.index', [
            'conversations' => $this->sidebarConversations($request->user()->id),
            'activeConversation' => $conversation,
            'messages' => $conversation->messages,
            'starterQuestions' => $this->starterQuestions(),
        ]);
    }

    private function conversationQuery(int $userId)
    {
        return ChatbotConversation::query()->where('user_id', $userId);
    }

    private function sidebarConversations(int $userId)
    {
        return $this->conversationQuery($userId)
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(12)
            ->get();
    }

    private function starterQuestions(): array
    {
        return [
            'What is PaperTrail?',
            'Where is my PR?',
            'How do I create a Purchase Request?',
            'What happens after submitting a PR?',
            'What does Pending BAC Chair Signature mean?',
            'Where can I upload supporting documents?',
            'How do I continue my draft document?',
        ];
    }

    private function logOpened(Request $request, ?ChatbotConversation $conversation = null): void
    {
        AuditLogger::log('Smart Procurement Chatbot', 'chatbot_opened', 'User opened the Smart Procurement Chatbot.', null, null, null, 'info', [
            'user_id' => $request->user()->id,
            'role' => $request->user()->assignedRole?->name ?? $request->user()->role,
            'conversation_id' => $conversation?->id,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
