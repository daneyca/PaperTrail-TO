@once
    <div class="ai-completeness-modal ai-metadata-modal no-print" data-ai-metadata-modal hidden>
        <div class="ai-completeness-modal__backdrop" data-ai-metadata-close></div>
        <section class="ai-completeness-modal__dialog ai-metadata-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aiMetadataTitle">
            <header class="ai-completeness-modal__header">
                <div>
                    <p>AI Assistant</p>
                    <h2 id="aiMetadataTitle">Metadata Extraction Result</h2>
                </div>
                <button type="button" class="ai-completeness-modal__close" data-ai-metadata-close aria-label="Close metadata extraction result">&times;</button>
            </header>

            <div class="ai-completeness-modal__body">
                <div class="ai-completeness-loading" data-ai-metadata-loading>
                    <div class="ai-completeness-spinner" aria-hidden="true"></div>
                    <strong>Extracting metadata...</strong>
                    <span>PaperTrail is reading supported file text and classifying this procurement attachment.</span>
                </div>

                <div class="ai-completeness-error" data-ai-metadata-error hidden>
                    <strong>Metadata extraction is temporarily unavailable.</strong>
                    <span data-ai-metadata-error-text>Please continue reviewing this attachment manually.</span>
                </div>

                <div data-ai-metadata-result hidden>
                    <article class="ai-metadata-summary">
                        <div class="ai-metadata-summary__icon">AI</div>
                        <div>
                            <p class="ai-completeness-document" data-ai-metadata-attachment>Attachment</p>
                            <h3 data-ai-metadata-classification>Supporting Document</h3>
                            <span data-ai-metadata-confidence>Confidence: 0%</span>
                            <span class="ai-metadata-status" data-ai-metadata-status>Pending human review</span>
                        </div>
                    </article>

                    <div class="ai-metadata-grid">
                        <section class="ai-metadata-panel">
                            <h3>Extracted Information</h3>
                            <dl data-ai-metadata-fields></dl>
                        </section>

                        <section class="ai-metadata-panel">
                            <h3>Missing / Unavailable</h3>
                            <ul class="ai-completeness-list ai-completeness-list--warning" data-ai-metadata-missing></ul>
                        </section>
                    </div>

                    <div class="ai-completeness-recommendation">
                        <strong>Review Required</strong>
                        <span>AI metadata is advisory only. Accepting this result marks it reviewed but does not change official document records.</span>
                    </div>
                </div>
            </div>

            <footer class="ai-completeness-modal__footer">
                <span>AI extracts and classifies only. It does not modify, route, approve, or reject documents.</span>
                <div class="ai-completeness-footer-actions">
                    <button type="button" class="ai-completeness-footer-button" data-ai-metadata-cancel hidden>Cancel</button>
                    <button type="button" class="ai-completeness-footer-button ai-metadata-review-button" data-ai-metadata-reject hidden>Reject</button>
                    <button type="button" class="ai-completeness-footer-button ai-metadata-review-button ai-metadata-review-button--primary" data-ai-metadata-accept hidden>Accept Metadata</button>
                    <button type="button" class="ai-completeness-footer-button" data-ai-metadata-close>Close</button>
                </div>
            </footer>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                if (window.paperTrailAiMetadataReady) {
                    return;
                }

                window.paperTrailAiMetadataReady = true;

                const modal = document.querySelector('[data-ai-metadata-modal]');
                if (!modal) {
                    return;
                }

                const csrfToken = '{{ csrf_token() }}';
                const loading = modal.querySelector('[data-ai-metadata-loading]');
                const error = modal.querySelector('[data-ai-metadata-error]');
                const errorText = modal.querySelector('[data-ai-metadata-error-text]');
                const result = modal.querySelector('[data-ai-metadata-result]');
                const attachmentText = modal.querySelector('[data-ai-metadata-attachment]');
                const classificationText = modal.querySelector('[data-ai-metadata-classification]');
                const confidenceText = modal.querySelector('[data-ai-metadata-confidence]');
                const statusText = modal.querySelector('[data-ai-metadata-status]');
                const fieldsList = modal.querySelector('[data-ai-metadata-fields]');
                const missingList = modal.querySelector('[data-ai-metadata-missing]');
                const cancelButton = modal.querySelector('[data-ai-metadata-cancel]');
                const acceptButton = modal.querySelector('[data-ai-metadata-accept]');
                const rejectButton = modal.querySelector('[data-ai-metadata-reject]');
                let activeController = null;
                let activeTrigger = null;
                let activeTimeout = null;
                let activePayload = null;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const labelFor = (key) => String(key || '')
                    .replaceAll('_', ' ')
                    .replace(/\b\w/g, (letter) => letter.toUpperCase());

                const presentValue = (value) => {
                    if (value === null || value === undefined || value === '') {
                        return 'Not detected';
                    }

                    if (Array.isArray(value)) {
                        return value.length ? value.join(', ') : 'Not detected';
                    }

                    if (typeof value === 'object') {
                        return JSON.stringify(value);
                    }

                    return String(value);
                };

                const setState = (state) => {
                    loading.hidden = state !== 'loading';
                    error.hidden = state !== 'error';
                    result.hidden = state !== 'result';
                    cancelButton.hidden = state !== 'loading';
                    acceptButton.hidden = state !== 'result';
                    rejectButton.hidden = state !== 'result';
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

                const renderFields = (metadata) => {
                    const entries = Object.entries(metadata || {});
                    fieldsList.innerHTML = entries.length
                        ? entries.map(([key, value]) => `
                            <div>
                                <dt>${escapeHtml(labelFor(key))}</dt>
                                <dd>${escapeHtml(presentValue(value))}</dd>
                            </div>
                        `).join('')
                        : '<div><dt>Metadata</dt><dd>No metadata fields detected.</dd></div>';
                };

                const renderMissing = (items) => {
                    const values = Array.isArray(items) ? items.filter(Boolean) : [];
                    missingList.innerHTML = values.length
                        ? values.map((item) => `<li>${escapeHtml(item)}</li>`).join('')
                        : '<li>No missing metadata fields reported.</li>';
                };

                const renderResult = (payload, trigger) => {
                    activePayload = payload;
                    const classification = payload.classification || {};
                    const category = payload.classification_category || classification.category || 'Supporting Document';
                    const confidence = Number(payload.confidence_score ?? payload.classification_confidence ?? classification.confidence ?? 0);

                    attachmentText.textContent = trigger.dataset.attachmentName || 'Attachment';
                    classificationText.textContent = category;
                    confidenceText.textContent = `Confidence: ${Math.max(0, Math.min(100, confidence))}%`;
                    statusText.textContent = String(payload.status || 'pending_review').replaceAll('_', ' ');
                    renderFields(payload.extracted_metadata || {});
                    renderMissing(payload.missing_fields || classification.missing_fields || []);
                    setState('result');
                };

                const postReview = async (url, body = {}) => {
                    if (!url) {
                        return;
                    }

                    acceptButton.disabled = true;
                    rejectButton.disabled = true;

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(body),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            errorText.textContent = payload.message || 'Unable to review metadata result right now.';
                            setState('error');
                            return;
                        }

                        renderResult(payload, {
                            dataset: {
                                attachmentName: attachmentText.textContent,
                            },
                        });
                    } catch (reviewError) {
                        errorText.textContent = 'Unable to review metadata result right now.';
                        setState('error');
                    } finally {
                        acceptButton.disabled = false;
                        rejectButton.disabled = false;
                    }
                };

                document.addEventListener('click', async (event) => {
                    const closeButton = event.target.closest('[data-ai-metadata-close]');
                    if (closeButton) {
                        abortActiveRequest();
                        closeModal();
                        return;
                    }

                    const cancelAction = event.target.closest('[data-ai-metadata-cancel]');
                    if (cancelAction) {
                        abortActiveRequest();
                        errorText.textContent = 'Metadata extraction cancelled. You may try again.';
                        setState('error');
                        return;
                    }

                    const acceptAction = event.target.closest('[data-ai-metadata-accept]');
                    if (acceptAction && activePayload) {
                        await postReview(activePayload.accept_url);
                        return;
                    }

                    const rejectAction = event.target.closest('[data-ai-metadata-reject]');
                    if (rejectAction && activePayload) {
                        const reason = window.prompt('Optional rejection reason:') || '';
                        await postReview(activePayload.reject_url, { reason });
                        return;
                    }

                    const trigger = event.target.closest('[data-ai-metadata-trigger]');
                    if (!trigger) {
                        return;
                    }

                    event.preventDefault();
                    abortActiveRequest();
                    activePayload = null;
                    openModal();
                    setState('loading');
                    trigger.disabled = true;

                    const controller = new AbortController();
                    let didTimeout = false;
                    activeController = controller;
                    activeTrigger = trigger;

                    activeTimeout = window.setTimeout(() => {
                        didTimeout = true;
                        controller.abort();
                        errorText.textContent = 'Metadata extraction timed out. You may try again.';
                        setState('error');
                    }, 30000);

                    try {
                        const response = await fetch(trigger.dataset.extractUrl, {
                            method: 'POST',
                            signal: controller.signal,
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({}),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            errorText.textContent = payload.message || 'Metadata extraction is temporarily unavailable.';
                            setState('error');
                            return;
                        }

                        renderResult(payload, trigger);
                    } catch (requestError) {
                        if (didTimeout) {
                            errorText.textContent = 'Metadata extraction timed out. You may try again.';
                        } else if (requestError.name === 'AbortError') {
                            errorText.textContent = 'Metadata extraction cancelled. You may try again.';
                        } else {
                            errorText.textContent = 'Metadata extraction is temporarily unavailable.';
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
