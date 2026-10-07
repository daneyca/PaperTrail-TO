@once
    <div
        class="ai-completeness-modal no-print"
        data-ai-completeness-modal
        data-endpoint="{{ route('ai.document-completeness.check') }}"
        hidden
    >
        <div class="ai-completeness-modal__backdrop" data-ai-completeness-close></div>
        <section class="ai-completeness-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aiCompletenessTitle">
            <header class="ai-completeness-modal__header">
                <div>
                    <p>AI Assistant</p>
                    <h2 id="aiCompletenessTitle">Document Completeness Result</h2>
                </div>
                <button type="button" class="ai-completeness-modal__close" data-ai-completeness-close aria-label="Close AI completeness result">&times;</button>
            </header>

            <div class="ai-completeness-modal__body">
                <div class="ai-completeness-loading" data-ai-completeness-loading>
                    <div class="ai-completeness-spinner" aria-hidden="true"></div>
                    <strong>Analyzing document...</strong>
                    <span>PaperTrail is checking required fields, attachments, and configured requirements.</span>
                </div>

                <div class="ai-completeness-error" data-ai-completeness-error hidden>
                    <strong>AI completeness checking is temporarily unavailable.</strong>
                    <span data-ai-completeness-error-text>Please continue reviewing this document manually.</span>
                </div>

                <div data-ai-completeness-result hidden>
                    <article class="ai-completeness-score-card">
                        <div class="ai-completeness-score-ring" data-ai-completeness-score-ring>
                            <span data-ai-completeness-score>0%</span>
                        </div>
                        <div>
                            <p class="ai-completeness-document" data-ai-completeness-document>Document</p>
                            <p class="ai-completeness-status" data-ai-completeness-status>Completeness analysis completed.</p>
                        </div>
                    </article>

                    <div class="ai-completeness-grid">
                        <section class="ai-completeness-section">
                            <h3>Completed</h3>
                            <ul class="ai-completeness-list ai-completeness-list--completed" data-ai-completeness-completed></ul>
                        </section>
                        <section class="ai-completeness-section">
                            <h3>Missing</h3>
                            <ul class="ai-completeness-list ai-completeness-list--missing" data-ai-completeness-missing></ul>
                        </section>
                        <section class="ai-completeness-section">
                            <h3>Warnings</h3>
                            <ul class="ai-completeness-list ai-completeness-list--warning" data-ai-completeness-warnings></ul>
                        </section>
                    </div>

                    <div class="ai-completeness-recommendation">
                        <strong>Recommendation</strong>
                        <span data-ai-completeness-recommendation>Review highlighted requirements before routing this document.</span>
                    </div>
                </div>
            </div>

            <footer class="ai-completeness-modal__footer">
                <span>AI does not approve, reject, submit, or modify procurement documents.</span>
                <div class="ai-completeness-footer-actions">
                    <button type="button" class="ai-completeness-footer-button" data-ai-completeness-cancel hidden>Cancel</button>
                    <button type="button" class="ai-completeness-footer-button" data-ai-completeness-close>Close</button>
                </div>
            </footer>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                if (window.paperTrailAiCompletenessReady) {
                    return;
                }

                window.paperTrailAiCompletenessReady = true;

                const modal = document.querySelector('[data-ai-completeness-modal]');
                if (!modal) {
                    return;
                }

                const endpoint = modal.dataset.endpoint;
                const csrfToken = '{{ csrf_token() }}';
                const loading = modal.querySelector('[data-ai-completeness-loading]');
                const error = modal.querySelector('[data-ai-completeness-error]');
                const errorText = modal.querySelector('[data-ai-completeness-error-text]');
                const result = modal.querySelector('[data-ai-completeness-result]');
                const scoreRing = modal.querySelector('[data-ai-completeness-score-ring]');
                const scoreText = modal.querySelector('[data-ai-completeness-score]');
                const documentText = modal.querySelector('[data-ai-completeness-document]');
                const statusText = modal.querySelector('[data-ai-completeness-status]');
                const completedList = modal.querySelector('[data-ai-completeness-completed]');
                const missingList = modal.querySelector('[data-ai-completeness-missing]');
                const warningsList = modal.querySelector('[data-ai-completeness-warnings]');
                const recommendationText = modal.querySelector('[data-ai-completeness-recommendation]');
                const cancelButton = modal.querySelector('[data-ai-completeness-cancel]');
                let activeController = null;
                let activeTrigger = null;
                let activeTimeout = null;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const setList = (element, items, emptyText) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    element.innerHTML = values.length
                        ? values.map((item) => `<li>${escapeHtml(item)}</li>`).join('')
                        : `<li>${escapeHtml(emptyText)}</li>`;
                };

                const openModal = () => {
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                };

                const closeModal = () => {
                    modal.hidden = true;
                    document.body.style.overflow = '';
                };

                const clearActiveTimeout = () => {
                    if (activeTimeout) {
                        window.clearTimeout(activeTimeout);
                        activeTimeout = null;
                    }
                };

                const clearActiveRequest = () => {
                    clearActiveTimeout();
                    activeController = null;
                    activeTrigger = null;

                    if (cancelButton) {
                        cancelButton.hidden = true;
                    }
                };

                const abortActiveRequest = () => {
                    if (activeController) {
                        activeController.abort();
                    }

                    if (activeTrigger) {
                        activeTrigger.disabled = false;
                    }

                    clearActiveRequest();
                };

                const setState = (state) => {
                    loading.hidden = state !== 'loading';
                    error.hidden = state !== 'error';
                    result.hidden = state !== 'result';
                };

                const renderResult = (payload, trigger) => {
                    const score = Number(payload.score ?? 0);
                    const scoreClamped = Math.max(0, Math.min(100, score));
                    const angle = Math.round((scoreClamped / 100) * 360);
                    const tracking = payload.tracking_number || trigger.dataset.trackingNumber || `#${trigger.dataset.documentId}`;
                    const label = String(payload.document_type || trigger.dataset.documentType || 'Document')
                        .replaceAll('_', ' ')
                        .replace(/\b\w/g, (letter) => letter.toUpperCase());

                    scoreRing.style.setProperty('--score-angle', `${angle}deg`);
                    scoreText.textContent = `${scoreClamped}%`;
                    documentText.textContent = `${label} ${tracking}`;
                    statusText.textContent = scoreClamped >= 90
                        ? 'Completeness looks strong. Continue normal manual review.'
                        : 'Review the highlighted items before routing this document.';

                    setList(completedList, payload.completed_items || payload.completed_requirements, 'No configured required item has been marked complete yet.');
                    setList(missingList, payload.missing_requirements, 'No missing required requirement detected.');
                    setList(warningsList, payload.warnings, 'No warnings returned.');
                    recommendationText.textContent = payload.recommendation || 'Review highlighted requirements before routing this document.';
                    setState('result');
                };

                document.addEventListener('click', async (event) => {
                    const closeButton = event.target.closest('[data-ai-completeness-close]');
                    if (closeButton) {
                        abortActiveRequest();
                        closeModal();
                        return;
                    }

                    const cancelAction = event.target.closest('[data-ai-completeness-cancel]');
                    if (cancelAction) {
                        abortActiveRequest();
                        errorText.textContent = 'AI analysis cancelled. You may try again.';
                        setState('error');
                        return;
                    }

                    const trigger = event.target.closest('[data-ai-completeness-trigger]');
                    if (!trigger) {
                        return;
                    }

                    event.preventDefault();
                    abortActiveRequest();
                    openModal();
                    setState('loading');
                    trigger.disabled = true;

                    const controller = new AbortController();
                    let didTimeout = false;
                    activeController = controller;
                    activeTrigger = trigger;

                    if (cancelButton) {
                        cancelButton.hidden = false;
                    }

                    activeTimeout = window.setTimeout(() => {
                        didTimeout = true;
                        controller.abort();
                        errorText.textContent = 'AI analysis timed out. You may try again.';
                        setState('error');
                    }, 30000);

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            signal: controller.signal,
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                document_type: trigger.dataset.documentType,
                                document_id: trigger.dataset.documentId,
                            }),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            errorText.textContent = payload.message || 'AI completeness checking is temporarily unavailable.';
                            setState('error');
                            return;
                        }

                        renderResult(payload, trigger);
                    } catch (requestError) {
                        if (didTimeout) {
                            errorText.textContent = 'AI analysis timed out. You may try again.';
                        } else if (requestError.name === 'AbortError') {
                            errorText.textContent = 'AI analysis cancelled. You may try again.';
                        } else {
                            errorText.textContent = 'AI completeness checking is temporarily unavailable.';
                        }

                        setState('error');
                    } finally {
                        if (activeController === controller) {
                            clearActiveRequest();
                        }

                        trigger.disabled = false;
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !modal.hidden) {
                        abortActiveRequest();
                        closeModal();
                    }
                });
            })();
        </script>
    @endpush
@endonce
