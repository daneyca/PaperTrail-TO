@extends('layouts.dashboard')

@section('title', 'AI Assistant | PaperTrail')

@section('content')
    @php
        $user = auth()->user();
        $activeConversationId = $activeConversation?->id;
        $hasChatMessages = $messages->isNotEmpty();
    @endphp

    <section class="assistant-page assistant-workspace assistant-workspace--full">
        <div class="assistant-page-toolbar" aria-label="Assistant actions">
            <a class="assistant-new-chat assistant-new-chat--top" href="{{ route('assistant.index') }}">
                <span aria-hidden="true">+</span>
                New Chat
            </a>

            <div class="assistant-connection-card" aria-label="OpenAI connection status">
                <strong class="assistant-status-pill">OpenAI Connected</strong>
            </div>
        </div>

        <section class="assistant-chat-card assistant-workspace__center assistant-workspace__main" aria-label="Smart Procurement Chatbot">
            <div class="assistant-chat-header">
                <div>
                    <span>PaperTrail AI Assistant</span>
                    <h1>Procurement Help Desk</h1>
                    <p>Ask about PaperTrail routing, requirements, signatures, notifications, and document tracking.</p>
                </div>
            </div>

            <div id="chatMessages" class="assistant-messages assistant-chat-messages" data-assistant-messages>
                @if ($messages->isEmpty())
                    <div class="assistant-message is-assistant ai">
                        <div class="assistant-avatar" aria-hidden="true">
                            <x-papertrail.icon name="bot" />
                        </div>
                        <div class="assistant-bubble">
                            <span>Assistant</span>
                            <p>Hello! I can help you with PaperTrail workflows, documents, routing, signatures, notifications, and tracking.</p>
                        </div>
                    </div>
                @else
                    @foreach ($messages as $message)
                        @php
                            $trackingPayload = is_array($message->payload ?? null) ? $message->payload : [];
                            $isTrackingResponse = $message->role !== 'user'
                                && ($message->response_type ?? 'text') === 'document_tracking'
                                && ! empty($trackingPayload);
                        @endphp

                        @if ($isTrackingResponse)
                            <div class="assistant-message is-assistant ai assistant-message--tracking">
                                <div class="assistant-avatar" aria-hidden="true">
                                    <x-papertrail.icon name="bot" />
                                </div>
                                <article class="assistant-tracking-card" aria-label="Document tracking result">
                                    <div class="assistant-tracking-card__header">
                                        <div>
                                            <span>Document Tracking</span>
                                            <strong>{{ $trackingPayload['document_type'] ?? 'Procurement Document' }}</strong>
                                            <small>{{ $trackingPayload['tracking_number'] ?? 'Not recorded' }}</small>
                                        </div>
                                        <span class="assistant-tracking-card__status">{{ $trackingPayload['current_status'] ?? 'Not recorded' }}</span>
                                    </div>

                                    @if (! empty($trackingPayload['title']))
                                        <p class="assistant-tracking-card__title">{{ $trackingPayload['title'] }}</p>
                                    @endif

                                    <div class="assistant-tracking-card__current">
                                        <span>Current Location</span>
                                        <strong>{{ $trackingPayload['current_holder'] ?? 'Not currently assigned' }}</strong>
                                        <small>{{ $trackingPayload['current_stage'] ?? 'Current Stage' }}</small>
                                    </div>

                                    <x-documents.tracking-progress
                                        :document="$trackingPayload"
                                        :timeline="$trackingPayload['timeline'] ?? []"
                                        :current-stage="$trackingPayload['current_stage'] ?? null"
                                    />

                                    <x-documents.tracking-history
                                        :timeline="$trackingPayload['timeline'] ?? []"
                                        :next-step="$trackingPayload['next_step'] ?? null"
                                    />

                                    @if (filled($message->message))
                                        <p class="assistant-tracking-card__note">{{ $message->message }}</p>
                                    @endif
                                </article>
                            </div>
                        @else
                            <div class="assistant-message {{ $message->role === 'user' ? 'is-user' : 'is-assistant ai' }}">
                                <div class="assistant-avatar" aria-hidden="true">
                                    @if ($message->role === 'user')
                                        {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                                    @else
                                        <x-papertrail.icon name="bot" />
                                    @endif
                                </div>
                                <div class="assistant-bubble">
                                    <span>{{ $message->role === 'user' ? 'You' : 'Assistant' }}</span>
                                    <p>{{ $message->message }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="assistant-loading" data-assistant-loading hidden aria-hidden="true" aria-live="polite">
                <span></span>
                <span></span>
                <span></span>
                Thinking through the procurement flow...
            </div>

            <div class="assistant-suggestions-container {{ $hasChatMessages ? 'is-hidden' : '' }}" data-assistant-suggestions>
                <div id="suggestionsPanel" class="assistant-suggestions assistant-suggestions-panel" aria-label="Starter questions">
                    @foreach ($starterQuestions as $question)
                        <button type="button" class="assistant-suggestion-btn" data-assistant-suggestion="{{ $question }}">{{ $question }}</button>
                    @endforeach
                </div>
            </div>

            <button
                type="button"
                class="assistant-suggestions-reopen {{ $hasChatMessages ? '' : 'is-hidden' }}"
                data-assistant-suggestions-reopen
            >
                Show suggestions
            </button>

            <p class="assistant-error" data-assistant-error hidden>AI Assistant is temporarily unavailable. Please try again later.</p>

            <form class="assistant-form assistant-input-area" method="POST" action="{{ route('assistant.message') }}" data-assistant-form>
                @csrf
                <input type="hidden" name="conversation_id" value="{{ $activeConversationId }}" data-assistant-conversation-id>
                <button class="assistant-attach-button" type="button" aria-label="Attach document" title="Attach document">
                    <x-papertrail.icon name="upload" />
                </button>
                <label class="sr-only" for="assistantMessage">Ask the AI Assistant</label>
                <textarea
                    id="assistantMessage"
                    name="message"
                    rows="2"
                    maxlength="1500"
                    placeholder="Ask a PaperTrail question: PR status, missing documents, routing, signatures, or next steps..."
                    required
                    data-assistant-input
                ></textarea>
                <button type="submit" data-assistant-send>
                    <span>Send</span>
                </button>
            </form>
        </section>

    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-assistant-form]');
            const input = document.querySelector('[data-assistant-input]');
            const sendButton = document.querySelector('[data-assistant-send]');
            const messages = document.querySelector('[data-assistant-messages]');
            const loading = document.querySelector('[data-assistant-loading]');
            const error = document.querySelector('[data-assistant-error]');
            const conversationInput = document.querySelector('[data-assistant-conversation-id]');
            const suggestions = document.querySelector('[data-assistant-suggestions]');
            const suggestionsReopen = document.querySelector('[data-assistant-suggestions-reopen]');

            if (!form || !input || !messages) {
                return;
            }

            const setLoading = (isLoading) => {
                if (!loading) {
                    return;
                }

                loading.hidden = !isLoading;
                loading.setAttribute('aria-hidden', String(!isLoading));
            };

            setLoading(false);
            error.hidden = true;
            sendButton.disabled = false;

            const scrollChatToBottom = () => {
                window.requestAnimationFrame(() => {
                    messages.scrollTop = messages.scrollHeight;

                    if (typeof messages.scrollTo === 'function') {
                        messages.scrollTo({
                            top: messages.scrollHeight,
                            behavior: 'smooth',
                        });
                    } else {
                        messages.scrollTop = messages.scrollHeight;
                    }

                    window.setTimeout(() => {
                        messages.scrollTop = messages.scrollHeight;
                    }, 120);
                });
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const compactTimelineStage = (value) => String(value ?? 'workflow-step')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'workflow-step';

            const renderAssistantText = (value) => {
                const escaped = escapeHtml(value);

                return escaped
                    .replace(/^[-*]\s+(.+)$/gm, '<span class="assistant-md-list-item">$1</span>')
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/`(.+?)`/g, '<code>$1</code>')
                    .replace(/\n/g, '<br>');
            };

            const appendMessage = (role, message) => {
                const isUser = role === 'user';
                const avatar = isUser ? '{{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}' : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3m-6 6H3m18 0h-3M7.5 7.5 5.4 5.4m11.1 2.1 2.1-2.1M7 21h10a3 3 0 0 0 3-3v-5a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v5a3 3 0 0 0 3 3Zm2-8h.01M15 13h.01M9 17h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

                const renderedMessage = isUser ? escapeHtml(message) : renderAssistantText(message);

                messages.insertAdjacentHTML('beforeend', `
                    <div class="assistant-message ${isUser ? 'is-user' : 'is-assistant ai'}">
                        <div class="assistant-avatar" aria-hidden="true">${avatar}</div>
                        <div class="assistant-bubble">
                            <span>${isUser ? 'You' : 'Assistant'}</span>
                            <p>${renderedMessage}</p>
                        </div>
                    </div>
                `);

                scrollChatToBottom();
            };

            const assistantAvatar = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3m-6 6H3m18 0h-3M7.5 7.5 5.4 5.4m11.1 2.1 2.1-2.1M7 21h10a3 3 0 0 0 3-3v-5a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v5a3 3 0 0 0 3 3Zm2-8h.01M15 13h.01M9 17h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

            const hasAny = (haystack, needles) => {
                const source = String(haystack || '').toLowerCase();

                return needles.some((needle) => source.includes(needle));
            };

            const progressIcon = (state) => {
                if (state === 'completed') {
                    return '&#10003;';
                }

                return state === 'current' ? '&#9679;' : '&#9675;';
            };

            const progressStagesFor = (data) => {
                const typeText = `${data.document_type || ''} ${data.tracking_number || ''}`.toLowerCase();
                const timeline = Array.isArray(data.timeline) ? data.timeline : [];
                const currentText = [
                    data.current_status || '',
                    data.current_stage || '',
                    data.current_holder || '',
                ].join(' ').toLowerCase();
                const timelineText = timeline.map((item) => [
                    item.label || '',
                    item.status || '',
                    item.stage || '',
                    item.location || '',
                ].join(' ')).join(' ').toLowerCase();

                let labels = ['Draft Created', 'Submitted', 'PR Number Assigned', 'BAC Review', 'Approved', 'Completed'];
                let keywords = [
                    ['draft', 'created', 'recorded'],
                    ['submitted', 'pending pr number', 'pending pr number assignment'],
                    ['pr number', 'number assigned', 'numbering'],
                    ['bac', 'secretariat', 'validation', 'review', 'resolution'],
                    ['approved', 'approval', 'hope', 'confirmed'],
                    ['completed', 'closed'],
                ];

                if (hasAny(typeText, ['purchase order', 'po-'])) {
                    labels = ['Draft Created', 'Submitted', 'Supplier Processing', 'Delivery', 'Inspection', 'Completed'];
                    keywords = [
                        ['draft', 'created', 'recorded'],
                        ['submitted', 'routed'],
                        ['supplier', 'processing', 'purchase order', 'po approved', 'issued'],
                        ['delivery', 'delivered', 'dispatch'],
                        ['inspection', 'acceptance', 'accepted'],
                        ['completed', 'closed'],
                    ];
                } else if (hasAny(typeText, ['bac resolution', 'resolution', 'bac-res', 'bac res'])) {
                    labels = ['Draft Created', 'BAC Review', 'BAC Member Review', 'BAC Chair Signature', 'HOPE Approval', 'Completed'];
                    keywords = [
                        ['draft', 'created', 'recorded'],
                        ['bac review', 'resolution', 'secretariat'],
                        ['member', 'deliberation'],
                        ['chair', 'signature', 'confirmed'],
                        ['hope', 'approval', 'approved'],
                        ['completed', 'closed'],
                    ];
                } else if (hasAny(typeText, ['project procurement management plan', 'ppmp'])) {
                    labels = ['Draft Created', 'Submitted', 'BAC Review', 'APP Consolidation', 'Accepted', 'Completed'];
                    keywords = [
                        ['draft', 'created', 'recorded'],
                        ['submitted', 'pending ppmp review'],
                        ['bac', 'review', 'secretariat', 'under ppmp review'],
                        ['app consolidation', 'consolidation'],
                        ['accepted', 'approved'],
                        ['completed', 'closed'],
                    ];
                }

                let currentIndex = 0;
                const sourceText = currentText || timelineText;

                keywords.forEach((stageKeywords, index) => {
                    if (hasAny(sourceText, stageKeywords)) {
                        currentIndex = index;
                    }
                });

                if (currentIndex === 0 && timelineText) {
                    keywords.forEach((stageKeywords, index) => {
                        if (hasAny(timelineText, stageKeywords)) {
                            currentIndex = Math.max(currentIndex, index);
                        }
                    });
                }

                return labels.map((label, index) => ({
                    label,
                    status: index < currentIndex ? 'completed' : (index === currentIndex ? 'current' : 'pending'),
                }));
            };

            const renderProgressStage = (stage) => `
                <div class="progress-stage ${escapeHtml(stage.status || 'pending')}" data-compact-timeline-stage="${compactTimelineStage(stage.label)}">
                    <div class="progress-circle" aria-hidden="true">${progressIcon(stage.status || 'pending')}</div>
                    <div class="progress-label">${escapeHtml(stage.label || 'Workflow Step')}</div>
                </div>
            `;

            const historyIcon = (state) => {
                if (state === 'current') {
                    return '&#9679;';
                }

                return state === 'pending' || state === 'upcoming' ? '&#9675;' : '&#10003;';
            };

            const renderHistoryItem = (item) => {
                const state = item.state || 'completed';
                const date = item.date_display ? `<span>${escapeHtml(item.date_display)}</span>` : '';
                const location = item.location ? `<small>${escapeHtml(item.location)}</small>` : '';

                return `
                    <div class="history-item is-${escapeHtml(state)}">
                        <div class="history-marker" aria-hidden="true">${historyIcon(state)}</div>
                        <div class="history-content">
                            <h4>${escapeHtml(item.label || 'Workflow Update')}</h4>
                            ${date ? `<p>${date}</p>` : ''}
                            ${location ? `<p>${location}</p>` : ''}
                        </div>
                    </div>
                `;
            };

            const appendTrackingMessage = (trackingData, aiMessage) => {
                const data = trackingData || {};
                const progress = progressStagesFor(data).map(renderProgressStage).join('');
                const timeline = Array.isArray(data.timeline) && data.timeline.length
                    ? data.timeline.map(renderHistoryItem).join('')
                    : '<div class="tracking-history__empty">No routing history has been recorded yet.</div>';
                const nextStep = data.next_step
                    ? `
                        <div class="history-item is-upcoming">
                            <div class="history-marker" aria-hidden="true">&#9675;</div>
                            <div class="history-content">
                                <h4>Next Step</h4>
                                <p>${escapeHtml(data.next_step)}</p>
                            </div>
                        </div>
                    `
                    : '';
                const title = data.title ? `<p class="assistant-tracking-card__title">${escapeHtml(data.title)}</p>` : '';
                const note = aiMessage ? `<p class="assistant-tracking-card__note">${escapeHtml(aiMessage)}</p>` : '';

                messages.insertAdjacentHTML('beforeend', `
                    <div class="assistant-message is-assistant ai assistant-message--tracking">
                        <div class="assistant-avatar" aria-hidden="true">${assistantAvatar}</div>
                        <article class="assistant-tracking-card" aria-label="Document tracking result">
                            <div class="assistant-tracking-card__header">
                                <div>
                                    <span>Document Tracking</span>
                                    <strong>${escapeHtml(data.document_type || 'Procurement Document')}</strong>
                                    <small>${escapeHtml(data.tracking_number || 'Not recorded')}</small>
                                </div>
                                <span class="assistant-tracking-card__status">${escapeHtml(data.current_status || 'Not recorded')}</span>
                            </div>
                            ${title}
                            <div class="assistant-tracking-card__current">
                                <span>Current Location</span>
                                <strong>${escapeHtml(data.current_holder || 'Not currently assigned')}</strong>
                                <small>${escapeHtml(data.current_stage || 'Current Stage')}</small>
                            </div>
                            <div class="document-tracking-progress-wrap">
                                <div class="document-tracking-progress" aria-label="Major document tracking stages">
                                    ${progress}
                                </div>
                            </div>
                            <div class="tracking-history">
                                <div class="tracking-history__heading">Activity History</div>
                                ${timeline}
                                ${nextStep}
                            </div>
                            ${note}
                        </article>
                    </div>
                `);

                scrollChatToBottom();
            };

            window.addEventListener('load', scrollChatToBottom);
            scrollChatToBottom();

            const hideSuggestions = () => {
                suggestions?.classList.add('is-hidden');
                suggestionsReopen?.classList.remove('is-hidden');
            };

            const showSuggestions = () => {
                suggestions?.classList.remove('is-hidden');
                suggestionsReopen?.classList.add('is-hidden');
            };

            suggestionsReopen?.addEventListener('click', () => {
                showSuggestions();
                input.focus();
            });

            document.querySelectorAll('[data-assistant-suggestion]').forEach((button) => {
                button.addEventListener('click', () => {
                    input.value = button.dataset.assistantSuggestion || '';
                    hideSuggestions();

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', {
                            bubbles: true,
                            cancelable: true,
                        }));
                    }
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const message = input.value.trim();

                if (!message) {
                    return;
                }

                hideSuggestions();
                appendMessage('user', message);
                scrollChatToBottom();
                input.value = '';
                error.hidden = true;
                setLoading(true);
                sendButton.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            message,
                            conversation_id: conversationInput.value || null,
                        }),
                    });

                    const data = await response.json();

                    if (data.conversation_id) {
                        conversationInput.value = data.conversation_id;
                        if (window.history && data.history_url && window.location.pathname === '{{ route('assistant.index', [], false) }}') {
                            window.history.replaceState({}, '', data.history_url);
                        }
                    }

                    if (data.type === 'document_tracking' && data.tracking_data) {
                        appendTrackingMessage(data.tracking_data, data.ai_message || data.response || '');
                    } else {
                        appendMessage('assistant', data.response || 'AI Assistant is temporarily unavailable. Please try again later.');
                    }
                    scrollChatToBottom();

                    if (!response.ok || !data.ok) {
                        error.hidden = false;
                    }
                } catch (requestError) {
                    appendMessage('assistant', 'AI Assistant is temporarily unavailable. Please try again later.');
                    scrollChatToBottom();
                    error.hidden = false;
                } finally {
                    setLoading(false);
                    sendButton.disabled = false;
                    input.focus();
                }
            });
        })();
    </script>
@endpush
