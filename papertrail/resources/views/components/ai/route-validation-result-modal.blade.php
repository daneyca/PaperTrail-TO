@once
    <div
        class="ai-completeness-modal ai-route-validation-modal no-print"
        data-ai-route-validation-modal
        data-endpoint="{{ route('ai.route-validation.check') }}"
        hidden
    >
        <div class="ai-completeness-modal__backdrop" data-ai-route-validation-close></div>
        <section class="ai-completeness-modal__dialog ai-route-validation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aiRouteValidationTitle">
            <header class="ai-completeness-modal__header">
                <div>
                    <p>AI Assistant</p>
                    <h2 id="aiRouteValidationTitle">AI Check Routing</h2>
                </div>
                <button type="button" class="ai-completeness-modal__close" data-ai-route-validation-close aria-label="Close AI route validation result">&times;</button>
            </header>

            <div class="ai-completeness-modal__body">
                <div class="ai-completeness-loading" data-ai-route-validation-loading>
                    <div class="ai-completeness-spinner" aria-hidden="true"></div>
                    <strong>Validating route...</strong>
                    <span>PaperTrail is checking the current holder, status, route rules, and expected next workflow step.</span>
                </div>

                <div class="ai-completeness-error" data-ai-route-validation-error hidden>
                    <strong>AI route validation is temporarily unavailable.</strong>
                    <span data-ai-route-validation-error-text>Please continue reviewing this document manually.</span>
                </div>

                <div data-ai-route-validation-result hidden>
                    <article class="ai-route-validation-summary" data-ai-route-validation-status-card>
                        <div class="ai-route-validation-summary__icon" data-ai-route-validation-status-icon>OK</div>
                        <div>
                            <p class="ai-completeness-document" data-ai-route-validation-document>Document</p>
                            <h3 data-ai-route-validation-status>Route Looks Valid</h3>
                            <span data-ai-route-validation-message>The current route is consistent with PaperTrail workflow data.</span>
                        </div>
                        <div class="ai-route-validation-confidence">
                            <strong data-ai-route-validation-confidence>--%</strong>
                            <span>Confidence Score</span>
                        </div>
                    </article>

                    <div class="ai-route-validation-grid">
                        <section class="ai-route-validation-panel">
                            <h3>Current Route</h3>
                            <dl>
                                <div>
                                    <dt>Status</dt>
                                    <dd data-ai-route-current-status>Not recorded</dd>
                                </div>
                                <div>
                                    <dt>Stage</dt>
                                    <dd data-ai-route-current-stage>Not recorded</dd>
                                </div>
                                <div>
                                    <dt>Holder</dt>
                                    <dd data-ai-route-current-holder>Not recorded</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="ai-route-validation-panel">
                            <h3>Expected Route</h3>
                            <dl>
                                <div>
                                    <dt>Next Status</dt>
                                    <dd data-ai-route-next-status>Not mapped</dd>
                                </div>
                                <div>
                                    <dt>Next Stage</dt>
                                    <dd data-ai-route-next-stage>Not mapped</dd>
                                </div>
                                <div>
                                    <dt>Next Office / Role</dt>
                                    <dd data-ai-route-next-holder>Not mapped</dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <div class="ai-route-validation-grid">
                        <section class="ai-route-validation-panel">
                            <h3>Validation Checks</h3>
                            <ul class="ai-route-validation-checks" data-ai-route-validation-checks></ul>
                        </section>

                        <section class="ai-route-validation-panel">
                            <h3>Warnings</h3>
                            <ul class="ai-completeness-list ai-completeness-list--warning" data-ai-route-validation-warnings></ul>
                        </section>
                    </div>

                    <section class="ai-route-validation-history">
                        <h3>Recent Route History</h3>
                        <ol data-ai-route-validation-history></ol>
                    </section>

                    <div class="ai-completeness-recommendation">
                        <strong>Recommendation</strong>
                        <span data-ai-route-validation-recommendation>Continue with the next configured workflow action.</span>
                    </div>
                </div>
            </div>

            <footer class="ai-completeness-modal__footer">
                <span>AI validates routing guidance only. It does not approve, reject, submit, or reroute documents.</span>
                <div class="ai-completeness-footer-actions">
                    <button type="button" class="ai-completeness-footer-button" data-ai-route-validation-cancel hidden>Cancel</button>
                    <button type="button" class="ai-completeness-footer-button" data-ai-route-validation-close>Close</button>
                </div>
            </footer>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                if (window.paperTrailAiRouteValidationReady) {
                    return;
                }

                window.paperTrailAiRouteValidationReady = true;

                const modal = document.querySelector('[data-ai-route-validation-modal]');
                if (!modal) {
                    return;
                }

                const endpoint = modal.dataset.endpoint;
                const csrfToken = '{{ csrf_token() }}';
                const loading = modal.querySelector('[data-ai-route-validation-loading]');
                const error = modal.querySelector('[data-ai-route-validation-error]');
                const errorText = modal.querySelector('[data-ai-route-validation-error-text]');
                const result = modal.querySelector('[data-ai-route-validation-result]');
                const statusCard = modal.querySelector('[data-ai-route-validation-status-card]');
                const statusIcon = modal.querySelector('[data-ai-route-validation-status-icon]');
                const documentText = modal.querySelector('[data-ai-route-validation-document]');
                const statusText = modal.querySelector('[data-ai-route-validation-status]');
                const messageText = modal.querySelector('[data-ai-route-validation-message]');
                const confidenceText = modal.querySelector('[data-ai-route-validation-confidence]');
                const currentStatus = modal.querySelector('[data-ai-route-current-status]');
                const currentStage = modal.querySelector('[data-ai-route-current-stage]');
                const currentHolder = modal.querySelector('[data-ai-route-current-holder]');
                const nextStatus = modal.querySelector('[data-ai-route-next-status]');
                const nextStage = modal.querySelector('[data-ai-route-next-stage]');
                const nextHolder = modal.querySelector('[data-ai-route-next-holder]');
                const checksList = modal.querySelector('[data-ai-route-validation-checks]');
                const warningsList = modal.querySelector('[data-ai-route-validation-warnings]');
                const historyList = modal.querySelector('[data-ai-route-validation-history]');
                const recommendationText = modal.querySelector('[data-ai-route-validation-recommendation]');
                const cancelButton = modal.querySelector('[data-ai-route-validation-cancel]');
                let activeController = null;
                let activeTrigger = null;
                let activeTimeout = null;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const safeStatus = (value) => ['passed', 'warning', 'info'].includes(value) ? value : 'info';

                const setState = (state) => {
                    loading.hidden = state !== 'loading';
                    error.hidden = state !== 'error';
                    result.hidden = state !== 'result';
                };

                const setText = (element, value, fallback = 'Not recorded') => {
                    element.textContent = value || fallback;
                };

                const setWarnings = (items) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    warningsList.innerHTML = values.length
                        ? values.map((item) => `<li>${escapeHtml(item)}</li>`).join('')
                        : '<li>No route warnings detected.</li>';
                };

                const setChecks = (items) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    checksList.innerHTML = values.length
                        ? values.map((item) => {
                            const state = safeStatus(item.status);
                            return `
                                <li class="ai-route-validation-check ai-route-validation-check--${state}">
                                    <span>${state === 'passed' ? 'OK' : state === 'warning' ? '!' : 'i'}</span>
                                    <div>
                                        <strong>${escapeHtml(item.label || 'Validation check')}</strong>
                                        <p>${escapeHtml(item.detail || '')}</p>
                                    </div>
                                </li>
                            `;
                        }).join('')
                        : '<li class="ai-route-validation-check ai-route-validation-check--info"><span>i</span><div><strong>No checks returned.</strong><p>Continue manual review.</p></div></li>';
                };

                const setHistory = (items) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    historyList.innerHTML = values.length
                        ? values.slice(-6).map((item) => {
                            const movement = [item.from, item.to].filter(Boolean).join(' to ');
                            return `
                                <li>
                                    <strong>${escapeHtml(item.action || 'Workflow action')}</strong>
                                    <span>${escapeHtml(item.date || 'Date not recorded')}</span>
                                    ${movement ? `<p>${escapeHtml(movement)}</p>` : ''}
                                    ${item.actor ? `<p>${escapeHtml(item.actor)}</p>` : ''}
                                    ${item.comments ? `<p>${escapeHtml(item.comments)}</p>` : ''}
                                </li>
                            `;
                        }).join('')
                        : '<li><strong>No routing history yet.</strong><span>History will appear after workflow movement is recorded.</span></li>';
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

                const renderResult = (payload, trigger) => {
                    const tracking = payload.tracking_number || trigger.dataset.trackingNumber || `#${trigger.dataset.documentId}`;
                    const label = String(payload.document_type || trigger.dataset.documentType || 'Document')
                        .replaceAll('_', ' ')
                        .replace(/\b\w/g, (letter) => letter.toUpperCase());
                    const routeStatus = payload.status || 'valid';

                    statusCard.dataset.status = routeStatus;
                    statusIcon.textContent = routeStatus === 'complete' ? 'OK' : routeStatus === 'warning' ? '!' : 'OK';
                    documentText.textContent = `${label} ${tracking}`;
                    statusText.textContent = payload.status_label || 'Route Looks Valid';
                    messageText.textContent = payload.ai_message || 'The current route is consistent with PaperTrail workflow data.';
                    confidenceText.textContent = `${Math.max(0, Math.min(100, Number(payload.confidence_score ?? 0) || 0))}%`;
                    setText(currentStatus, payload.current_status_label);
                    setText(currentStage, payload.current_stage);
                    setText(currentHolder, payload.current_holder);
                    setText(nextStatus, payload.expected_next_status_label, 'Not mapped');
                    setText(nextStage, payload.expected_next_stage, 'Not mapped');
                    setText(nextHolder, payload.expected_next_holder, 'Not mapped');
                    setChecks(payload.checks);
                    setWarnings(payload.warnings);
                    setHistory(payload.history);
                    recommendationText.textContent = payload.recommendation || 'Continue with the next configured workflow action.';
                    setState('result');
                };

                document.addEventListener('click', async (event) => {
                    const closeButton = event.target.closest('[data-ai-route-validation-close]');
                    if (closeButton) {
                        abortActiveRequest();
                        closeModal();
                        return;
                    }

                    const cancelAction = event.target.closest('[data-ai-route-validation-cancel]');
                    if (cancelAction) {
                        abortActiveRequest();
                        errorText.textContent = 'AI route validation cancelled. You may try again.';
                        setState('error');
                        return;
                    }

                    const trigger = event.target.closest('[data-ai-route-validation-trigger]');
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
                        errorText.textContent = 'AI route validation timed out. You may try again.';
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
                            errorText.textContent = payload.message || 'AI route validation is temporarily unavailable.';
                            setState('error');
                            return;
                        }

                        renderResult(payload, trigger);
                    } catch (requestError) {
                        if (didTimeout) {
                            errorText.textContent = 'AI route validation timed out. You may try again.';
                        } else if (requestError.name === 'AbortError') {
                            errorText.textContent = 'AI route validation cancelled. You may try again.';
                        } else {
                            errorText.textContent = 'AI route validation is temporarily unavailable.';
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
