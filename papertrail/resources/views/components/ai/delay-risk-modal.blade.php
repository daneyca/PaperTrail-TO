@once
    <div
        class="ai-completeness-modal ai-delay-risk-modal no-print"
        data-ai-delay-risk-modal
        hidden
    >
        <div class="ai-completeness-modal__backdrop" data-ai-delay-risk-close></div>
        <section class="ai-completeness-modal__dialog ai-delay-risk-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aiDelayRiskTitle">
            <header class="ai-completeness-modal__header">
                <div>
                    <p>AI Assistant</p>
                    <h2 id="aiDelayRiskTitle">Delay Risk and Anomaly Result</h2>
                </div>
                <button type="button" class="ai-completeness-modal__close" data-ai-delay-risk-close aria-label="Close AI delay risk result">&times;</button>
            </header>

            <div class="ai-completeness-modal__body" data-ai-delay-risk-body>
                <div class="ai-completeness-loading" data-ai-delay-risk-loading>
                    <div class="ai-completeness-spinner" aria-hidden="true"></div>
                    <strong>Analyzing delay risk...</strong>
                    <span>PaperTrail is checking workflow age, routing history, signature status, and activity movement.</span>
                </div>

                <div class="ai-completeness-error" data-ai-delay-risk-error hidden>
                    <strong>AI delay risk analysis is temporarily unavailable.</strong>
                    <span data-ai-delay-risk-error-text>Please continue monitoring this document manually.</span>
                </div>

                <div data-ai-delay-risk-result hidden>
                    <article class="ai-delay-risk-summary" data-ai-delay-risk-summary>
                        <div class="ai-delay-risk-summary__icon" data-ai-delay-risk-level>LOW</div>
                        <div>
                            <p class="ai-completeness-document" data-ai-delay-risk-document>Document</p>
                            <h3 data-ai-delay-risk-label>Low Delay Risk</h3>
                            <span data-ai-delay-risk-message>This document is still within the normal monitoring window.</span>
                        </div>
                    </article>

                    <div class="ai-delay-risk-grid">
                        <section class="ai-delay-risk-panel">
                            <h3>Current Movement</h3>
                            <dl>
                                <div>
                                    <dt>Status</dt>
                                    <dd data-ai-delay-current-status>Not recorded</dd>
                                </div>
                                <div>
                                    <dt>Stage</dt>
                                    <dd data-ai-delay-current-stage>Not recorded</dd>
                                </div>
                                <div>
                                    <dt>Holder</dt>
                                    <dd data-ai-delay-current-holder>Not recorded</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="ai-delay-risk-panel">
                            <h3>Delay Signals</h3>
                            <dl>
                                <div>
                                    <dt>Waiting</dt>
                                    <dd data-ai-delay-waiting>0 days</dd>
                                </div>
                                <div>
                                    <dt>Last Activity</dt>
                                    <dd data-ai-delay-last-activity>Not recorded</dd>
                                </div>
                                <div>
                                    <dt>Signatures</dt>
                                    <dd data-ai-delay-signatures>0 open, 0 signed</dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <div class="ai-delay-risk-grid">
                        <section class="ai-delay-risk-panel">
                            <h3>Risk Factors</h3>
                            <ul class="ai-completeness-list ai-completeness-list--warning" data-ai-delay-factors></ul>
                        </section>

                        <section class="ai-delay-risk-panel">
                            <h3>Anomaly Flags</h3>
                            <ul class="ai-delay-risk-list" data-ai-delay-anomalies></ul>
                        </section>
                    </div>

                    <section class="ai-delay-risk-history">
                        <h3>Recent Activity</h3>
                        <ol data-ai-delay-history></ol>
                    </section>

                    <div class="ai-completeness-recommendation">
                        <strong>Next Step</strong>
                        <span data-ai-delay-next-step>Check the configured workflow step manually.</span>
                    </div>

                    <div class="ai-delay-risk-recommendation">
                        <strong>Recommendation</strong>
                        <span data-ai-delay-recommendation>Continue normal monitoring.</span>
                    </div>
                </div>
            </div>

            <footer class="ai-completeness-modal__footer">
                <span>AI analyzes delay risk only. It does not approve, reject, submit, or reroute documents.</span>
                <div class="ai-completeness-footer-actions">
                    <button type="button" class="ai-completeness-footer-button" data-ai-delay-risk-cancel hidden>Cancel</button>
                    <button type="button" class="ai-completeness-footer-button" data-ai-delay-risk-close>Close</button>
                </div>
            </footer>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                if (window.paperTrailAiDelayRiskReady) {
                    return;
                }

                window.paperTrailAiDelayRiskReady = true;

                const modal = document.querySelector('[data-ai-delay-risk-modal]');
                if (!modal) {
                    return;
                }

                const csrfToken = '{{ csrf_token() }}';
                const modalBody = modal.querySelector('[data-ai-delay-risk-body]');
                const loading = modal.querySelector('[data-ai-delay-risk-loading]');
                const error = modal.querySelector('[data-ai-delay-risk-error]');
                const errorText = modal.querySelector('[data-ai-delay-risk-error-text]');
                const result = modal.querySelector('[data-ai-delay-risk-result]');
                const summary = modal.querySelector('[data-ai-delay-risk-summary]');
                const riskLevel = modal.querySelector('[data-ai-delay-risk-level]');
                const riskLabel = modal.querySelector('[data-ai-delay-risk-label]');
                const documentText = modal.querySelector('[data-ai-delay-risk-document]');
                const messageText = modal.querySelector('[data-ai-delay-risk-message]');
                const currentStatus = modal.querySelector('[data-ai-delay-current-status]');
                const currentStage = modal.querySelector('[data-ai-delay-current-stage]');
                const currentHolder = modal.querySelector('[data-ai-delay-current-holder]');
                const waiting = modal.querySelector('[data-ai-delay-waiting]');
                const lastActivity = modal.querySelector('[data-ai-delay-last-activity]');
                const signatures = modal.querySelector('[data-ai-delay-signatures]');
                const factorsList = modal.querySelector('[data-ai-delay-factors]');
                const anomaliesList = modal.querySelector('[data-ai-delay-anomalies]');
                const historyList = modal.querySelector('[data-ai-delay-history]');
                const nextStep = modal.querySelector('[data-ai-delay-next-step]');
                const recommendationText = modal.querySelector('[data-ai-delay-recommendation]');
                const cancelButton = modal.querySelector('[data-ai-delay-risk-cancel]');
                let activeController = null;
                let activeTrigger = null;
                let activeTimeout = null;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const riskText = (value) => {
                    const normalized = String(value || 'LOW').toUpperCase();
                    return ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].includes(normalized) ? normalized : 'LOW';
                };

                const setState = (state) => {
                    loading.hidden = state !== 'loading';
                    error.hidden = state !== 'error';
                    result.hidden = state !== 'result';

                    if (modalBody) {
                        modalBody.scrollTop = 0;
                    }
                };

                const setText = (element, value, fallback = 'Not recorded') => {
                    element.textContent = value || fallback;
                };

                const setList = (element, items, emptyText) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    element.innerHTML = values.length
                        ? values.map((item) => `<li>${escapeHtml(item)}</li>`).join('')
                        : `<li>${escapeHtml(emptyText)}</li>`;
                };

                const setHistory = (items) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    historyList.innerHTML = values.length
                        ? values.slice(-6).reverse().map((item) => {
                            const movement = [item.from, item.to].filter(Boolean).join(' to ');

                            return `
                                <li>
                                    <span></span>
                                    <div>
                                        <strong>${escapeHtml(item.action || 'Workflow action')}</strong>
                                        <p>${escapeHtml(item.date || 'Date not recorded')}</p>
                                        ${movement ? `<p>${escapeHtml(movement)}</p>` : ''}
                                        ${item.actor ? `<p>${escapeHtml(item.actor)}</p>` : ''}
                                        ${item.comments ? `<p>${escapeHtml(item.comments)}</p>` : ''}
                                    </div>
                                </li>
                            `;
                        }).join('')
                        : '<li><span></span><div><strong>No activity history yet.</strong><p>Recorded movement will appear after workflow activity is saved.</p></div></li>';
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
                    const level = riskText(payload.risk_level);
                    const days = Number(payload.days_waiting ?? payload.delay_days ?? 0);
                    const signatureSummary = payload.signature_summary || {};
                    const openSignatures = Number(signatureSummary.open_count || 0);
                    const signedSignatures = Number(signatureSummary.signed_count || 0);

                    summary.dataset.risk = level;
                    riskLevel.textContent = level;
                    riskLabel.textContent = payload.risk_label || `${level} Delay Risk`;
                    documentText.textContent = `${label} ${tracking}`;
                    messageText.textContent = payload.ai_message || 'Delay risk analysis completed using PaperTrail workflow data.';
                    setText(currentStatus, payload.current_status_label);
                    setText(currentStage, payload.current_stage);
                    setText(currentHolder, payload.current_holder);
                    waiting.textContent = `${days} day${days === 1 ? '' : 's'}`;
                    setText(lastActivity, payload.last_activity_at);
                    signatures.textContent = `${openSignatures} open, ${signedSignatures} signed`;
                    setList(factorsList, payload.risk_factors, 'No delay risk factors detected.');
                    setList(anomaliesList, payload.anomalies, 'No anomaly flags detected.');
                    setHistory(payload.history);
                    setText(nextStep, payload.next_expected_step, 'Check the configured workflow step manually.');
                    recommendationText.textContent = payload.recommendation || 'Continue normal monitoring.';
                    setState('result');
                };

                document.addEventListener('click', async (event) => {
                    const closeButton = event.target.closest('[data-ai-delay-risk-close]');
                    if (closeButton) {
                        abortActiveRequest();
                        closeModal();
                        return;
                    }

                    const cancelAction = event.target.closest('[data-ai-delay-risk-cancel]');
                    if (cancelAction) {
                        abortActiveRequest();
                        errorText.textContent = 'AI delay risk analysis cancelled. You may try again.';
                        setState('error');
                        return;
                    }

                    const trigger = event.target.closest('[data-ai-delay-risk-trigger]');
                    if (!trigger) {
                        return;
                    }

                    event.preventDefault();
                    abortActiveRequest();
                    openModal();
                    setState('loading');
                    trigger.disabled = true;

                    const endpoint = trigger.dataset.endpoint;
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
                        errorText.textContent = 'AI delay risk analysis timed out. You may try again.';
                        setState('error');
                    }, 30000);

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            signal: controller.signal,
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            errorText.textContent = payload.message || 'AI delay risk analysis is temporarily unavailable.';
                            setState('error');
                            return;
                        }

                        renderResult(payload, trigger);
                    } catch (requestError) {
                        if (didTimeout) {
                            errorText.textContent = 'AI delay risk analysis timed out. You may try again.';
                        } else if (requestError.name === 'AbortError') {
                            errorText.textContent = 'AI delay risk analysis cancelled. You may try again.';
                        } else {
                            errorText.textContent = 'AI delay risk analysis is temporarily unavailable.';
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
