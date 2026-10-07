@props([
    'hidden' => false,
])

@unless ($hidden)
    @once
        <div {{ $attributes->class(['pt-chatbot-widget no-print'])->merge(['data-chatbot-widget' => true]) }}>
            <button class="pt-chatbot-button group" type="button" data-chatbot-toggle data-chatbot-drag-handle aria-label="Open AI Assistant" aria-expanded="false" aria-controls="ptChatbotPanel">
                <span class="pt-chatbot-robot" aria-hidden="true">
                    <span class="pt-chatbot-robot__antenna"></span>
                    <span class="pt-chatbot-robot__head">
                        <span class="pt-chatbot-robot__face">
                            <span></span>
                            <span></span>
                        </span>
                    </span>
                    <span class="pt-chatbot-robot__body">
                        <span>AI</span>
                    </span>
                    <span class="pt-chatbot-robot__dot pt-chatbot-robot__dot--one"></span>
                    <span class="pt-chatbot-robot__dot pt-chatbot-robot__dot--two"></span>
                    <span class="pt-chatbot-robot__dot pt-chatbot-robot__dot--three"></span>
                </span>
                <span class="pt-chatbot-button-copy">
                    <strong>AI Assistant</strong>
                    <small>Online</small>
                </span>
            </button>

            <section class="pt-chatbot-panel" id="ptChatbotPanel" data-chatbot-panel hidden>
                <div class="pt-chatbot-panel-header">
                    <div>
                        <span>PaperTrail AI</span>
                        <strong>Smart Procurement Assistant</strong>
                    </div>
                    <button type="button" data-chatbot-close aria-label="Close chatbot panel">&times;</button>
                </div>

                <div class="pt-chatbot-panel-body">
                    <p class="pt-chatbot-message is-system">Ask about Purchase Requests, APP, BAC Resolution, Purchase Orders, Inspection, e-signatures, or document routing.</p>
                    <p class="pt-chatbot-message is-note">The assistant gives workflow guidance only. LGU users still perform official actions.</p>
                </div>

                <div class="pt-chatbot-panel-footer">
                    <a class="pt-chatbot-open-link" href="{{ route('assistant.index') }}">Open Assistant</a>
                </div>
            </section>
        </div>
    @endonce
@endunless
