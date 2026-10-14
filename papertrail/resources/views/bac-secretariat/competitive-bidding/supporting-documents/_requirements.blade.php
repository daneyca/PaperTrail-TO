<div class="cb-support-requirement-groups">
    @foreach ($sections as $section => $items)
        <details class="cb-support-requirement-group" open>
            <summary>
                <span>{{ $section }}</span>
                <small>{{ $items->where('status', 'verified')->count() }} / {{ $items->count() }} verified</small>
            </summary>

            <div class="cb-support-requirement-list">
                @foreach ($items as $item)
                    @php
                        $statusClass = str_replace('_', '-', $item->status);
                        $conditionalInactive = $item->isPostAwardConditional() && ! $item->isEffectivelyRequired();
                    @endphp
                    <article class="cb-support-requirement cb-support-requirement--{{ $statusClass }}">
                        <div class="cb-support-requirement-number">{{ $item->guide_number }}</div>

                        <div class="cb-support-requirement-info">
                            <div class="cb-support-requirement-title">
                                <strong>{{ $item->requirement_name }}</strong>
                                @if ($conditionalInactive)
                                    <span class="cb-support-condition">Required after award</span>
                                @endif
                            </div>
                            <span class="cb-support-source">Source: {{ $item->document_source }}</span>
                            @if ($item->submittedBy && $item->submitted_at)
                                <small>Submitted by {{ $item->submittedBy->name }} ({{ $item->submittedBy->user_id }}) &middot; Version {{ $item->source_version }} &middot; {{ $item->submitted_at->format('M d, Y h:i A') }}</small>
                            @endif
                            @if ($item->checkedBy)
                                <small>Reviewed by {{ $item->checkedBy->name }} · {{ $item->checked_at?->format('M d, Y h:i A') }}</small>
                            @endif
                        </div>

                        <div class="cb-support-files">
                            @forelse ($item->attachments as $attachment)
                                <div class="cb-support-file">
                                    <span title="{{ $attachment->displayName() }}">{{ $attachment->displayName() }}</span>
                                    <small>{{ $attachment->fileSizeHuman() }}</small>
                                    <div class="cb-support-file-actions">
                                        <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.view', [$record, $item, $attachment]) }}" target="_blank" class="cb-support-icon-button" title="View file" aria-label="View file">
                                            <x-papertrail.icon name="view" />
                                        </a>
                                        <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.download', [$record, $item, $attachment]) }}" class="cb-support-icon-button" title="Download file" aria-label="Download file">
                                            <x-papertrail.icon name="download" />
                                        </a>
                                        <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.destroy', [$record, $item, $attachment]) }}" onsubmit="return confirm('Remove this attachment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cb-support-icon-button cb-support-icon-button--danger" title="Remove file" aria-label="Remove file">
                                                <x-papertrail.icon name="delete" />
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                @if ($item->hasSystemDocument())
                                    <div class="cb-support-system-file">
                                        <span>Completed system document</span>
                                        <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.system-document', [$record, $item]) }}" target="_blank" class="cb-support-icon-button" title="Open system document" aria-label="Open system document">
                                            <x-papertrail.icon name="view" />
                                        </a>
                                    </div>
                                @else
                                    <span class="cb-support-no-file">No file attached</span>
                                @endif
                            @endforelse

                            <form method="POST" enctype="multipart/form-data" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.store', [$record, $item]) }}" class="cb-support-upload-form">
                                @csrf
                                <input type="file" name="attachment" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" aria-label="Attach file for guide item {{ $item->guide_number }}">
                                <button type="submit" class="cb-support-icon-button cb-support-icon-button--upload" title="Upload file" aria-label="Upload file">
                                    <x-papertrail.icon name="upload" />
                                </button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]) }}" class="cb-support-review-form">
                            @csrf
                            @method('PATCH')
                            <label>
                                <span class="sr-only">Status for {{ $item->requirement_name }}</span>
                                <select name="status" class="cb-support-select cb-support-select--{{ $statusClass }}">
                                    @foreach ($itemStatuses as $value => $label)
                                        <option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="sr-only">Remarks for {{ $item->requirement_name }}</span>
                                <input name="remarks" value="{{ $item->remarks }}" maxlength="2000" placeholder="Remarks">
                            </label>
                            <button type="submit" class="cb-support-secondary cb-support-secondary--compact">
                                <x-papertrail.icon name="check" />
                                Save
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
