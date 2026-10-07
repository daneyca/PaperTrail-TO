@props([
    'title' => 'Recent Drafts',
    'drafts' => collect(),
    'documentTypeLabel' => 'document',
    'createPermission' => true,
    'showOnCreatePage' => false,
    'maxItems' => 5,
])

@php
    $draftCollection = collect($drafts)->values();
    $pageSize = max(1, (int) $maxItems);
    $draftTotal = $draftCollection->count();
    $draftPageCount = max(1, (int) ceil($draftTotal / $pageSize));
    $draftPlural = \Illuminate\Support\Str::plural($documentTypeLabel);
    $emptyTitle = 'No draft ' . $draftPlural . ' yet.';

    if ($createPermission) {
        \App\Services\AuditLogger::log('Draft Documents', 'document_draft_panel_viewed', 'User viewed a same-type draft panel.', null, null, null, 'info', [
            'document_type' => $documentTypeLabel,
            'draft_count' => $draftTotal,
            'context' => $showOnCreatePage ? 'create' : 'index',
        ]);
    }
@endphp

@if ($createPermission)
    <section class="document-records-card document-drafts-panel document-draft-manager-card {{ $showOnCreatePage ? 'document-draft-manager-card--create' : '' }} {{ $draftCollection->isEmpty() ? 'is-empty' : 'has-drafts' }} no-print" aria-label="{{ $title }}">
        <header>
            <div>
                @unless ($showOnCreatePage && $draftCollection->isEmpty())
                    <span class="section-label">DRAFT RECORDS</span>
                @endunless
                <h2>{{ $title }}</h2>
                @if ($draftCollection->isNotEmpty() || ! $showOnCreatePage)
                    <p>{{ $draftCollection->isNotEmpty() ? 'Continue recent draft documents without leaving this module.' : $emptyTitle }}</p>
                @endif
                @unless ($showOnCreatePage && $draftCollection->isEmpty())
                    <span class="document-records-type">{{ $documentTypeLabel }}</span>
                @endunless
            </div>
            <strong class="document-records-count">{{ $draftTotal }}</strong>
        </header>

        @if ($draftCollection->isNotEmpty())
            <div
                class="document-draft-file-list"
                data-document-draft-list
                data-page-size="{{ $pageSize }}"
                data-total="{{ $draftTotal }}"
            >
                @foreach ($draftCollection as $draft)
                    @php
                        $documentNumber = $draft['number'] ?? 'Draft';
                        $documentTitle = $draft['title'] ?? $documentTypeLabel . ' Draft';
                        $updatedAt = $draft['updated_at'] ?? null;
                        $updatedLabel = $updatedAt ? $updatedAt->format('M d, Y') : 'N/A';
                        $updatedFullLabel = $updatedAt ? $updatedAt->format('M d, Y h:i A') : 'N/A';
                    @endphp
                    <article class="document-draft-file-row" data-draft-row data-draft-index="{{ $loop->index }}">
                        <span class="document-draft-file-icon" aria-hidden="true">
                            <x-papertrail.icon name="document" />
                        </span>

                        <div class="document-draft-file-main">
                            <strong>{{ $documentNumber }}</strong>
                            <span>{{ $documentTitle }}</span>
                            @if (! empty($draft['prepared_by']))
                                <small>By {{ $draft['prepared_by'] }}</small>
                            @endif
                        </div>

                        <span class="document-status-badge document-status-draft">Draft</span>

                        <time class="document-draft-file-date" datetime="{{ $updatedAt ? $updatedAt->toDateString() : '' }}" title="{{ $updatedFullLabel }}">
                            {{ $updatedLabel }}
                        </time>

                        <div class="document-draft-file-actions">
                            @if (! empty($draft['edit_url']))
                                <x-ui.action-button
                                    :href="$draft['edit_url']"
                                    icon="edit"
                                    label="Continue"
                                    tooltip="Continue draft"
                                    variant="edit"
                                />
                            @endif
                            @if (! empty($draft['show_url']))
                                <x-ui.action-button
                                    :href="$draft['show_url']"
                                    icon="view"
                                    label="View"
                                    tooltip="View draft"
                                    variant="view"
                                    :icon-only="true"
                                />
                            @endif
                            @if (! empty($draft['print_url']))
                                <x-ui.action-button
                                    :href="$draft['print_url']"
                                    icon="print"
                                    label="Print"
                                    tooltip="Print draft"
                                    variant="print"
                                    target="_blank"
                                    rel="noopener"
                                    :icon-only="true"
                                />
                            @endif
                            @if (! empty($draft['download_url']))
                                <x-ui.action-button
                                    :href="$draft['download_url']"
                                    icon="download"
                                    label="Download"
                                    tooltip="Download draft"
                                    variant="download"
                                    target="_blank"
                                    rel="noopener"
                                    data-pt-download-confirm
                                    data-confirm-title="Download Document?"
                                    data-confirm="This file contains official procurement records."
                                    data-confirm-label="Download"
                                    data-confirm-type="download"
                                    :icon-only="true"
                                />
                            @endif
                            @if (! empty($draft['submit_url']))
                                <form method="POST" action="{{ $draft['submit_url'] }}" data-confirm="Submit this draft for processing?" data-confirm-title="Submit Draft?" data-confirm-label="Confirm Submission" data-confirm-type="submit">
                                    @csrf
                                    @if (($draft['submit_method'] ?? 'POST') !== 'POST')
                                        @method($draft['submit_method'])
                                    @endif
                                    <x-ui.action-button
                                        type="submit"
                                        icon="upload"
                                        label="Submit"
                                        tooltip="Submit draft"
                                        variant="success"
                                        :icon-only="true"
                                    />
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach

                <footer class="document-draft-pagination" data-draft-pagination @if ($draftPageCount <= 1) hidden @endif>
                    <span data-draft-pagination-summary>
                        Showing 1-{{ min($pageSize, $draftTotal) }} of {{ $draftTotal }} drafts
                    </span>
                    <div class="document-draft-pagination-controls">
                        <button type="button" data-draft-page-prev>Previous</button>
                        @for ($page = 1; $page <= $draftPageCount; $page++)
                            <button type="button" data-draft-page-button data-page="{{ $page }}">{{ $page }}</button>
                        @endfor
                        <button type="button" data-draft-page-next>Next</button>
                    </div>
                </footer>
            </div>
        @else
            <div class="document-records-empty document-drafts-empty-compact">
                <strong>{{ $emptyTitle }}</strong>
            </div>
        @endif
    </section>

    @once
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('[data-document-draft-list]').forEach((list) => {
                        const rows = [...list.querySelectorAll('[data-draft-row]')];
                        const pageSize = Math.max(1, Number.parseInt(list.dataset.pageSize || '5', 10));
                        const pagination = list.querySelector('[data-draft-pagination]');
                        const summary = list.querySelector('[data-draft-pagination-summary]');
                        const prev = list.querySelector('[data-draft-page-prev]');
                        const next = list.querySelector('[data-draft-page-next]');
                        const pageButtons = [...list.querySelectorAll('[data-draft-page-button]')];
                        const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
                        let currentPage = 1;

                        if (!rows.length || !pagination || pageCount <= 1) {
                            rows.forEach((row) => row.hidden = false);
                            return;
                        }

                        function renderPage(page) {
                            currentPage = Math.min(Math.max(1, page), pageCount);
                            const start = (currentPage - 1) * pageSize;
                            const end = Math.min(start + pageSize, rows.length);

                            rows.forEach((row, index) => {
                                row.hidden = index < start || index >= end;
                            });

                            if (summary) {
                                summary.textContent = `Showing ${start + 1}-${end} of ${rows.length} drafts`;
                            }

                            if (prev) {
                                prev.disabled = currentPage === 1;
                            }

                            if (next) {
                                next.disabled = currentPage === pageCount;
                            }

                            pageButtons.forEach((button) => {
                                const isActive = Number.parseInt(button.dataset.page || '1', 10) === currentPage;
                                button.classList.toggle('is-active', isActive);
                                button.setAttribute('aria-current', isActive ? 'page' : 'false');
                            });
                        }

                        prev?.addEventListener('click', () => renderPage(currentPage - 1));
                        next?.addEventListener('click', () => renderPage(currentPage + 1));
                        pageButtons.forEach((button) => {
                            button.addEventListener('click', () => renderPage(Number.parseInt(button.dataset.page || '1', 10)));
                        });

                        renderPage(1);
                    });
                });
            </script>
        @endpush
    @endonce
@endif
