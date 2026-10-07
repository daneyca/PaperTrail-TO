<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcurementChatbotService
{
    private const FALLBACK_MESSAGE = 'AI Assistant is temporarily unavailable. Please try again later.';
    private const OUT_OF_SCOPE_MESSAGE = 'I can only help with PaperTrail, LGU procurement workflows, document tracking, routing, requirements, signatures, notifications, and related system tasks. Please ask a PaperTrail-related question.';

    private const PAPERTRAIL_TERMS = [
        'papertrail',
        'procurement',
        'purchase request',
        'pr',
        'ppmp',
        'app',
        'supplemental app',
        'bac',
        'resolution',
        'rfq',
        'abstract',
        'quotation',
        'purchase order',
        'po',
        'inspection',
        'acceptance',
        'document',
        'documents',
        'tracking',
        'route',
        'routing',
        'workflow',
        'approval',
        'approve',
        'returned',
        'pending',
        'status',
        'holder',
        'office',
        'signature',
        'sign',
        'e-signature',
        'requirements',
        'missing',
        'upload',
        'attachment',
        'notification',
        'delay risk',
        'ai check',
        'lgu',
        'tomas oppus',
    ];

    private const PAPERTRAIL_FOLLOW_UPS = [
        'what happens next',
        'what next',
        'next step',
        'where is my',
        'where is it',
        'how do i',
        'how can i',
        'what should i do',
        'what do i need',
        'is it complete',
        'why pending',
        'why returned',
        'who has it',
    ];

    private const GREETINGS = [
        'hi',
        'hello',
        'hey',
        'good morning',
        'good afternoon',
        'good evening',
        'thanks',
        'thank you',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly DocumentTrackingService $documentTracking,
    )
    {
    }

    public function sendMessage(User $user, string $message, ?ChatbotConversation $conversation = null): array
    {
        $message = trim($message);

        if ($message === '') {
            return [
                'ok' => false,
                'type' => 'text',
                'conversation' => $conversation,
                'response' => 'Please enter a question for the AI Assistant.',
                'tracking_data' => null,
                'ai_message' => null,
                'assistant_message' => null,
            ];
        }

        $conversation = $conversation ?: $this->createConversation($user, $message);

        $userMessage = $conversation->messages()->create([
            'role' => ChatbotMessage::ROLE_USER,
            'message' => $message,
        ]);

        $this->audit($user, 'chatbot_message_sent', 'User sent a message to the Smart Procurement Chatbot.', [
            'conversation_id' => $conversation->id,
            'message_id' => $userMessage->id,
        ]);

        if (! $this->isPaperTrailQuestion($message, $conversation)) {
            return $this->handleOutOfScopeQuestion($user, $conversation);
        }

        if ($this->isTrackingQuestion($message) || $this->containsTrackingNumber($message)) {
            return $this->handleTrackingQuestion($user, $message, $conversation);
        }

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'user',
                    'content' => $this->buildPrompt($user, $message, $conversation),
                ],
            ], [
                'temperature' => 0.2,
                'max_tokens' => 1800,
            ]);

            $assistantMessage = $conversation->messages()->create([
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'message' => $response,
            ]);

            $conversation->touch();

            $this->audit($user, 'chatbot_response_generated', 'Smart Procurement Chatbot generated a response.', [
                'conversation_id' => $conversation->id,
                'message_id' => $assistantMessage->id,
            ]);

            return [
                'ok' => true,
                'type' => 'text',
                'conversation' => $conversation,
                'response' => $response,
                'tracking_data' => null,
                'ai_message' => null,
                'assistant_message' => $assistantMessage,
            ];
        } catch (Throwable $exception) {
            Log::error('PaperTrail chatbot request failed.', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $assistantMessage = $conversation->messages()->create([
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'message' => self::FALLBACK_MESSAGE,
            ]);

            $conversation->touch();

            $this->audit($user, 'chatbot_failed', 'Smart Procurement Chatbot request failed safely.', [
                'conversation_id' => $conversation->id,
                'message_id' => $assistantMessage->id,
                'error_class' => $exception::class,
            ], 'warning');

            return [
                'ok' => false,
                'type' => 'text',
                'conversation' => $conversation,
                'response' => self::FALLBACK_MESSAGE,
                'tracking_data' => null,
                'ai_message' => null,
                'assistant_message' => $assistantMessage,
            ];
        }
    }

    private function handleTrackingQuestion(User $user, string $message, ChatbotConversation $conversation): array
    {
        $tracking = $this->documentTracking->getDocumentTracking($user, $message);

        if (! ($tracking['found'] ?? false) || ! ($tracking['authorized'] ?? false)) {
            $response = $tracking['message'] ?? 'No matching authorized document found.';

            $assistantMessage = $conversation->messages()->create([
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'message' => $response,
            ]);

            $conversation->touch();

            return [
                'ok' => true,
                'type' => 'text',
                'conversation' => $conversation,
                'response' => $response,
                'tracking_data' => null,
                'ai_message' => null,
                'assistant_message' => $assistantMessage,
            ];
        }

        $trackingData = $this->trackingPayload($tracking['document']);

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'user',
                    'content' => $this->buildTrackingPrompt($user, $message, $tracking['summary']),
                ],
            ], [
                'temperature' => 0,
                'max_tokens' => 280,
            ]);
        } catch (Throwable $exception) {
            Log::error('PaperTrail chatbot tracking explanation failed.', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $response = $this->formatTrackingResponse($tracking['document']);

            $this->audit($user, 'chatbot_tracking_failed', 'Smart Procurement Chatbot could not generate an AI tracking explanation, so it returned database tracking details.', [
                'conversation_id' => $conversation->id,
                'document_type' => $tracking['document']['document_type'] ?? null,
                'tracking_number' => $tracking['document']['tracking_number'] ?? null,
                'error_class' => $exception::class,
            ], 'warning');
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => ChatbotMessage::ROLE_ASSISTANT,
            'message' => $response,
            'response_type' => 'document_tracking',
            'payload' => $trackingData,
        ]);

        $conversation->touch();

        $this->audit($user, 'chatbot_tracking_response_generated', 'Smart Procurement Chatbot generated a document tracking response.', [
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'document_type' => $tracking['document']['document_type'] ?? null,
            'tracking_number' => $tracking['document']['tracking_number'] ?? null,
        ]);

        return [
            'ok' => true,
            'type' => 'document_tracking',
            'conversation' => $conversation,
            'response' => $response,
            'tracking_data' => $trackingData,
            'ai_message' => $response,
            'assistant_message' => $assistantMessage,
        ];
    }

    private function createConversation(User $user, string $message): ChatbotConversation
    {
        return ChatbotConversation::create([
            'user_id' => $user->id,
            'title' => Str::limit($message, 72, ''),
            'session_id' => $this->sessionId(),
        ]);
    }

    private function buildPrompt(User $user, string $message, ChatbotConversation $conversation): string
    {
        $context = $this->safeUserContext($user);
        $history = $this->recentHistory($conversation);

        return <<<PROMPT
You are PaperTrail AI Procurement Assistant for LGU Tomas Oppus.

You help users understand the PaperTrail procurement management system.
You must only answer questions about PaperTrail, LGU procurement workflows, document tracking, routing, requirements, signatures, notifications, and related system tasks.
If the user asks about unrelated topics, refuse briefly and ask for a PaperTrail-related question.

You may explain:
- Purchase Request workflow
- APP workflow
- BAC Resolution workflow
- Purchase Order workflow
- Inspection workflow
- E-signature workflow
- Document routing

You must not:
- approve documents
- make procurement decisions
- modify records
- bypass workflow
- reveal confidential information.

Always recommend following official procurement procedures.

Current safe user context:
- Role: {$context['role']}
- Role code: {$context['role_code']}
- Office: {$context['office']}
- PaperTrail area: {$context['role_guidance']}

Safety rules:
- Give guidance only. Do not claim that you performed an action in the system.
- Do not mention API keys, passwords, credentials, private files, or hidden records.
- Do not reveal data from documents unless the user explicitly provides that data in the chat.
- If the question asks for an approval, rejection, signature, or routing action, explain the correct PaperTrail page/action and remind the user that an authorized LGU official must perform it.
- Complete the answer fully. Be concise for simple questions, but use enough detail for workflow or how-to questions so the response is not cut or incomplete.

Recent conversation:
{$history}

User question:
{$message}
PROMPT;
    }

    private function buildTrackingPrompt(User $user, string $message, string $trackingSummary): string
    {
        $context = $this->safeUserContext($user);

        return <<<PROMPT
You are PaperTrail Document Tracking Assistant.

You explain the current location and progress of procurement documents.

The provided workflow data is the official source.
Do not invent statuses.
Do not change workflow decisions.
Do not approve or reject documents.
Do not reveal confidential attachments, private user details, credentials, or internal notes.
Write only a short, friendly explanation of the current document location and next action.
Do not format a timeline.
Do not repeat every workflow event.
Keep it to 1 or 2 concise sentences.

Current safe user context:
- Role: {$context['role']}
- Role code: {$context['role_code']}
- Office: {$context['office']}

Official PaperTrail tracking data:
{$trackingSummary}

User question:
{$message}
PROMPT;
    }

    private function formatTrackingResponse(array $tracking): string
    {
        $timeline = collect($tracking['timeline'] ?? [])
            ->map(function (array $event): string {
                $date = $event['date_display'] ?? 'No date recorded';
                $location = filled($event['location'] ?? null) ? "\n{$event['location']}" : '';

                $state = ($event['state'] ?? '') === 'current' ? 'Current' : 'Completed';

                return "{$state}: {$event['label']}\n{$date}{$location}";
            })
            ->implode("\n\n");

        $timeline = $timeline !== '' ? $timeline : 'No routing history has been recorded yet.';

        return <<<RESPONSE
Document:
{$tracking['document_type']}

Tracking Number:
{$tracking['tracking_number']}

Current Status:
{$tracking['current_status']}

Current Location:
{$tracking['current_holder']}

Timeline:
{$timeline}

Next Action:
{$tracking['next_step']}
RESPONSE;
    }

    private function trackingPayload(array $tracking): array
    {
        return [
            'document_type' => (string) ($tracking['document_type'] ?? 'Procurement Document'),
            'tracking_number' => (string) ($tracking['tracking_number'] ?? 'Not recorded'),
            'title' => (string) ($tracking['title'] ?? ''),
            'current_status' => (string) ($tracking['current_status'] ?? 'Not recorded'),
            'current_holder' => (string) ($tracking['current_holder'] ?? 'Not currently assigned'),
            'current_stage' => (string) ($tracking['current_stage'] ?? 'Current Stage'),
            'next_step' => (string) ($tracking['next_step'] ?? 'Continue the configured procurement workflow.'),
            'timeline' => collect($tracking['timeline'] ?? [])
                ->map(fn (array $event): array => [
                    'label' => (string) ($event['label'] ?? 'Workflow Update'),
                    'date_display' => (string) ($event['date_display'] ?? ''),
                    'location' => (string) ($event['location'] ?? ''),
                    'stage' => (string) ($event['stage'] ?? ''),
                    'status' => (string) ($event['status'] ?? ''),
                    'state' => (string) ($event['state'] ?? 'completed'),
                ])
                ->values()
                ->all(),
        ];
    }

    private function isPaperTrailQuestion(string $message, ChatbotConversation $conversation): bool
    {
        $value = Str::of($message)->lower()->squish()->toString();

        if ($this->containsTrackingNumber($message) || $this->matchesAny($value, self::PAPERTRAIL_TERMS)) {
            return true;
        }

        if ($this->matchesAny($value, self::GREETINGS)) {
            return true;
        }

        if ($this->matchesAny($value, self::PAPERTRAIL_FOLLOW_UPS)) {
            return $conversation->messages()
                ->where('role', ChatbotMessage::ROLE_ASSISTANT)
                ->exists()
                || $conversation->messages()->count() <= 1;
        }

        return false;
    }

    private function matchesAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strlen($needle) <= 3 && preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $value) === 1) {
                return true;
            }

            if (strlen($needle) <= 3) {
                continue;
            }

            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function handleOutOfScopeQuestion(User $user, ChatbotConversation $conversation): array
    {
        $assistantMessage = $conversation->messages()->create([
            'role' => ChatbotMessage::ROLE_ASSISTANT,
            'message' => self::OUT_OF_SCOPE_MESSAGE,
        ]);

        $conversation->touch();

        $this->audit($user, 'chatbot_out_of_scope_refused', 'Smart Procurement Chatbot refused an out-of-scope question.', [
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
        ], 'info');

        return [
            'ok' => true,
            'type' => 'text',
            'conversation' => $conversation,
            'response' => self::OUT_OF_SCOPE_MESSAGE,
            'tracking_data' => null,
            'ai_message' => null,
            'assistant_message' => $assistantMessage,
        ];
    }

    private function isTrackingQuestion(string $message): bool
    {
        $value = Str::lower($message);

        $hasTrackingPhrase = str_contains($value, 'where is')
            || str_contains($value, 'track')
            || str_contains($value, 'tracking')
            || str_contains($value, 'status of')
            || str_contains($value, 'current status')
            || str_contains($value, 'current location')
            || str_contains($value, 'who has')
            || str_contains($value, 'who is holding')
            || str_contains($value, 'where na')
            || str_contains($value, 'asa na');

        if (! $hasTrackingPhrase) {
            return false;
        }

        return preg_match('/\b(BAC[-\s]?RES|PPMP|APP|SAIP|SUPP[-\s]?APP|PR|PO|RFQ|ABSTRACT|ABS|IAR)[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d*\b/i', $message) === 1
            || str_contains($value, 'document')
            || preg_match('/\bpr\b/i', $message) === 1
            || preg_match('/\bpo\b/i', $message) === 1
            || str_contains($value, 'purchase request')
            || str_contains($value, 'purchase order')
            || str_contains($value, 'ppmp')
            || str_contains($value, 'app')
            || str_contains($value, 'bac resolution')
            || str_contains($value, 'rfq')
            || str_contains($value, 'abstract')
            || str_contains($value, 'inspection');
    }

    private function containsTrackingNumber(string $message): bool
    {
        return preg_match('/\bBAC[-\s]?RES[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i', $message) === 1
            || preg_match('/\b(PPMP|APP|SAIP|SUPP[-\s]?APP|PR|PO|RFQ|ABSTRACT|ABS|IAR)[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i', $message) === 1
            || preg_match('/\b[A-Z]{2,8}[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i', $message) === 1;
    }

    private function safeUserContext(User $user): array
    {
        $role = $user->assignedRole?->name ?? $user->role ?? 'Authenticated User';
        $roleCode = ($user->assignedRole?->code ?? $user->roleSlug()) ?: 'authenticated_user';
        $office = $user->assignedOffice?->name ?? $user->office ?? 'No assigned office';

        return [
            'role' => $role,
            'role_code' => $roleCode,
            'office' => $office,
            'role_guidance' => $this->roleGuidance((string) $role, (string) $roleCode),
        ];
    }

    private function roleGuidance(string $role, string $roleCode): string
    {
        $value = Str::lower($role.' '.$roleCode);

        if (str_contains($value, 'head') || str_contains($value, 'end user') || str_contains($value, 'head_office')) {
            return 'Explain PPMP, Purchase Request creation, drafts, submitted documents, returned documents, supporting files, RFQ, Abstract, Purchase Order, and Inspection / Acceptance from the office user point of view.';
        }

        if (str_contains($value, 'bac_secretariat') || str_contains($value, 'bac secretariat')) {
            return 'Explain BAC Secretariat routing, PPMP review, APP consolidation, BAC Resolution preparation, PR validation, and document monitoring.';
        }

        if (str_contains($value, 'bac chair')) {
            return 'Explain BAC Chair confirmation, BAC Resolution review, e-signature, approval pages, and returned documents.';
        }

        if (str_contains($value, 'approving') || str_contains($value, 'procuring entity')) {
            return 'Explain final approval, pending approvals, approved documents, returned documents, and approval reports.';
        }

        if (str_contains($value, 'budget')) {
            return 'Explain budget review, budget availability, returned budget documents, and routing to Accounting.';
        }

        if (str_contains($value, 'accounting')) {
            return 'Explain accounting review, obligation/accounting status, returned accounting documents, and accounting reports.';
        }

        if (str_contains($value, 'pr_numbering') || str_contains($value, 'numbering')) {
            return 'Explain PR number assignment, pending PR numbers, assigned PR numbers, and returning numbered requests to the requesting office.';
        }

        if (str_contains($value, 'admin')) {
            return 'Explain account management, offices, roles, audit trail, settings, document requirements, and system configuration.';
        }

        return 'Explain PaperTrail workflows and authenticated system usage.';
    }

    private function recentHistory(ChatbotConversation $conversation): string
    {
        $messages = $conversation->messages()
            ->whereIn('role', [ChatbotMessage::ROLE_USER, ChatbotMessage::ROLE_ASSISTANT])
            ->latest('id')
            ->limit(8)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'No previous messages.';
        }

        return $messages
            ->map(fn (ChatbotMessage $message) => Str::upper($message->role).': '.Str::limit($message->message, 900))
            ->implode("\n");
    }

    private function audit(User $user, string $action, string $description, array $metadata = [], string $severity = 'info'): void
    {
        AuditLogger::log('Smart Procurement Chatbot', $action, $description, null, null, null, $severity, array_merge([
            'user_id' => $user->id,
            'role' => $user->assignedRole?->name ?? $user->role,
            'timestamp' => now()->toDateTimeString(),
        ], $metadata));
    }

    private function sessionId(): ?string
    {
        if (app()->runningInConsole() || ! request()->hasSession()) {
            return null;
        }

        return request()->session()->getId();
    }
}
