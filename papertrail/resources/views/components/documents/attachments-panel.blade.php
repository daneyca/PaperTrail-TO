@props([
    'document',
    'documentType' => null,
    'attachments' => null,
    'canUpload' => null,
    'canDelete' => null,
    'title' => 'Supporting Documents',
    'id' => null,
])

@php
    use App\Models\DocumentAttachment;
    use App\Services\DocumentResolverService;
    use App\Services\RuleConfigurationService;
    use Illuminate\Support\Str;

    $resolver = app(DocumentResolverService::class);
    $ruleService = app(RuleConfigurationService::class);
    $normalizedType = $resolver->documentTypeFor($document, $documentType);
    $attachmentList = $attachments
        ? collect($attachments)
        : DocumentAttachment::query()
            ->forDocument($normalizedType, $document)
            ->active()
            ->with(['uploadedBy'])
            ->latest()
            ->get();
    $canUpload = $canUpload ?? $resolver->canUpload(auth()->user(), $document);
    $canDelete = $canDelete ?? true;
    $configuredAttachmentRules = $ruleService->getAttachmentRequirements($normalizedType, $document->status ?? null);
    $suggestedAttachments = $configuredAttachmentRules->isNotEmpty()
        ? $configuredAttachmentRules->map(function ($rule) {
            return [
                'label' => $rule->rule_name ?: ($rule->description ?: 'Attachment requirement'),
                'description' => $rule->description,
                'category' => $rule->attachment_category,
                'required' => $rule->is_required,
                'severity' => $rule->severity,
            ];
        })->all()
        : collect(config("papertrail_attachments.{$normalizedType}.required", []))
            ->map(fn ($label) => [
                'label' => $label,
                'description' => null,
                'category' => null,
                'required' => false,
                'severity' => 'warning',
            ])
            ->all();
    $categories = [
        'supporting_document' => 'Supporting Document',
        'scanned_document' => 'Scanned Document',
        'quotation' => 'Quotation',
        'eligibility_document' => 'Eligibility Document',
        'bac_document' => 'BAC Document',
        'signed_document' => 'Signed Document',
        'other' => 'Other',
    ];
    $searchableAttachmentText = Str::lower($attachmentList->map(function ($attachment) {
        return implode(' ', [
            $attachment->displayName(),
            $attachment->description,
            $attachment->attachment_category,
            $attachment->document_section,
        ]);
    })->implode(' '));
@endphp

<section @if ($id) id="{{ $id }}" @endif class="attachments-card no-print" aria-label="{{ $title }}">
    <div class="attachments-header">
        <div>
            <p class="attachments-eyebrow">OCR-ready Repository</p>
            <h2>{{ $title }}</h2>
            <span>Upload PDF, JPG, PNG, DOC, DOCX, XLS, or XLSX files linked to this document record.</span>
        </div>
        <span class="attachment-badge attachment-badge-info">Private</span>
    </div>

    @if ($suggestedAttachments)
        <div class="required-attachments-list">
            <strong>Required / Suggested Attachments</strong>
            <ul>
                @foreach ($suggestedAttachments as $requiredAttachment)
                    @php
                        $requirementLabel = $requiredAttachment['label'] ?? (string) $requiredAttachment;
                        $requirementDescription = $requiredAttachment['description'] ?? null;
                        $requirementCategory = $requiredAttachment['category'] ?? null;
                        $isUploaded = $attachmentList->isNotEmpty()
                            && (
                                Str::contains($searchableAttachmentText, Str::lower($requirementLabel))
                                || ($requirementCategory && Str::contains($searchableAttachmentText, Str::lower($requirementCategory)))
                            );
                    @endphp
                    <li>
                        <span>
                            {{ $requirementLabel }}
                            @if ($requirementDescription && $requirementDescription !== $requirementLabel)
                                <small>{{ $requirementDescription }}</small>
                            @endif
                        </span>
                        <span class="attachment-badge {{ $isUploaded ? 'attachment-badge-success' : (($requiredAttachment['required'] ?? false) ? 'attachment-badge-critical' : 'attachment-badge-warning') }}">
                            {{ $isUploaded ? 'Uploaded' : (($requiredAttachment['required'] ?? false) ? 'Required' : 'Suggested') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canUpload)
        <form
            class="attachments-upload-form"
            method="POST"
            action="{{ route('document-attachments.store', ['documentType' => $normalizedType, 'documentId' => $document->getKey()]) }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="attachments-field attachments-field--file">
                <label for="attachment-{{ $normalizedType }}-{{ $document->getKey() }}">File</label>
                <input id="attachment-{{ $normalizedType }}-{{ $document->getKey() }}" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                <small>PDF, JPG, PNG, DOC, DOCX, XLS, XLSX up to 10MB.</small>
                @error('attachment')
                    <small class="attachment-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="attachments-field">
                <label for="attachment-category-{{ $normalizedType }}-{{ $document->getKey() }}">Category</label>
                <select id="attachment-category-{{ $normalizedType }}-{{ $document->getKey() }}" name="attachment_category">
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="attachments-field attachments-field--description">
                <label for="attachment-description-{{ $normalizedType }}-{{ $document->getKey() }}">Description</label>
                <textarea id="attachment-description-{{ $normalizedType }}-{{ $document->getKey() }}" name="description" rows="2" placeholder="Optional notes for validation or OCR context"></textarea>
            </div>

            <div class="attachments-actions-row">
                <label class="attachments-checkbox">
                    <input type="checkbox" name="is_confidential" value="1">
                    <span>Confidential</span>
                </label>

                <button type="submit" class="attachments-upload-btn">Upload</button>
            </div>
        </form>
    @else
        <div class="attachments-readonly-note">
            Attachments are read-only for your current role or this document status.
        </div>
    @endif

    <div class="attachments-table-wrap">
        <table class="attachments-table">
            <thead>
                <tr>
                    <th>File Name</th>
                    <th>Category</th>
                    <th>Size</th>
                    <th>Uploaded By</th>
                    <th>Date Uploaded</th>
                    <th>OCR Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attachmentList as $attachment)
                    @php
                        $category = $attachment->attachment_category ?: $attachment->document_section ?: 'other';
                        $ocrStatus = $attachment->ocr_status ?: 'pending';
                        $canDeleteAttachment = $canDelete && $resolver->canDeleteAttachment(auth()->user(), $document, $attachment);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $attachment->displayName() }}</strong>
                            @if ($attachment->description)
                                <span>{{ $attachment->description }}</span>
                            @endif
                            <em>{{ $attachment->isOcrReady() ? 'Ready for OCR/AI extraction once AI service is enabled.' : 'Stored for reference. OCR not required.' }}</em>
                        </td>
                        <td>
                            <span class="attachment-badge attachment-badge-neutral">
                                {{ $categories[$category] ?? Str::of($category)->replace('_', ' ')->title() }}
                            </span>
                        </td>
                        <td>{{ $attachment->fileSizeHuman() }}</td>
                        <td>{{ $attachment->uploadedBy?->name ?? 'System' }}</td>
                        <td>{{ $attachment->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                        <td>
                            <span class="attachment-badge attachment-badge-{{ str_replace('_', '-', $ocrStatus) }}">
                                {{ Str::of($ocrStatus)->replace('_', ' ')->title() }}
                            </span>
                        </td>
                        <td>
                            <div class="attachment-actions">
                                <x-ui.action-button
                                    :href="$attachment->viewUrl()"
                                    icon="view"
                                    label="View Details"
                                    tooltip="View Details"
                                    variant="view"
                                    target="_blank"
                                    rel="noopener"
                                    icon-only
                                />
                                <x-ui.action-button
                                    :href="$attachment->downloadUrl()"
                                    icon="download"
                                    label="Download Document"
                                    tooltip="Download Document"
                                    variant="download"
                                    data-pt-download-confirm
                                    data-confirm-title="Download Document?"
                                    data-confirm="This file contains official procurement records."
                                    data-confirm-label="Download"
                                    data-confirm-type="download"
                                    icon-only
                                />
                                <x-ai.metadata-extract-button :attachment="$attachment" />
                                <x-ai.completeness-check-button
                                    :document-type="$normalizedType"
                                    :document-id="$document->getKey()"
                                    :tracking-number="$resolver->displayTrackingNumber($document)"
                                    label="Check Completeness"
                                    variant="inline-icon"
                                />
                                @if ($canDeleteAttachment)
                                    <form method="POST" action="{{ route('document-attachments.destroy', $attachment) }}" data-confirm="Delete this attachment record?" data-confirm-title="Delete Attachment?" data-confirm-label="Delete" data-confirm-type="danger">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.action-button
                                            type="submit"
                                            icon="delete"
                                            label="Delete Record"
                                            tooltip="Delete Record"
                                            variant="danger"
                                            icon-only
                                        />
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="attachments-empty">
                                <strong>No supporting documents uploaded yet.</strong>
                                <p>Upload scanned PDFs, images, spreadsheets, or office documents here when they are available.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
