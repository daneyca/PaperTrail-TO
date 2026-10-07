@extends('layouts.dashboard')

@section('title', $config['title'] . ' | PaperTrail')

@section('content')
    @php
        $settingsLinks = [
            ['label' => 'General', 'route' => 'admin.settings.index', 'active' => request()->routeIs('admin.settings.index', 'admin.settings.group', 'admin.settings.email')],
            ['label' => 'AI Rules', 'route' => 'admin.settings.ai-rules.index', 'active' => request()->routeIs('admin.settings.ai-rules.*')],
            ['label' => 'Document Requirements', 'route' => 'admin.settings.document-requirements.index', 'active' => request()->routeIs('admin.settings.document-requirements.*')],
            ['label' => 'Routing Rules', 'route' => 'admin.settings.routing-rules.index', 'active' => request()->routeIs('admin.settings.routing-rules.*')],
            ['label' => 'Delay Thresholds', 'route' => 'admin.settings.delay-thresholds.index', 'active' => request()->routeIs('admin.settings.delay-thresholds.*')],
            ['label' => 'Notification Templates', 'route' => 'admin.settings.notification-templates.index', 'active' => request()->routeIs('admin.settings.notification-templates.*')],
        ];

        $formatLabel = fn ($value) => filled($value) ? str($value)->replace('_', ' ')->title() : 'Any';
        $fieldValue = function ($record, string $field) {
            $value = old($field, $record ? $record->{$field} : null);

            if ($field === 'expected_output_schema' && is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            return $value;
        };
        $checkedValue = function ($record, string $field, bool $default = false) {
            $value = old($field, $record ? $record->{$field} : $default);

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        };
        $displayValue = function ($record, string $field) use ($formatLabel, $options) {
            if ($field === 'from_office_id') {
                return $record->fromOffice?->name ?? 'Any';
            }

            if ($field === 'to_office_id') {
                return $record->toOffice?->name ?? 'Any';
            }

            if ($field === 'notify_office_id') {
                return $record->notifyOffice?->name ?? 'Any';
            }

            $value = $record->{$field};

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            if ($field === 'document_type' && filled($value)) {
                return $options['documentTypes'][$value] ?? $formatLabel($value);
            }

            return filled($value) ? $value : 'Any';
        };
        $isTextArea = fn (string $field) => in_array($field, ['description', 'prompt_instruction', 'expected_output_schema', 'system_note', 'rule_description', 'body'], true);
        $isBoolean = fn (string $field) => in_array($field, ['is_required', 'is_active', 'requires_signature', 'requires_attachment_check'], true);
        $selectOptions = function (string $field) use ($options) {
            return match ($field) {
                'document_type' => $options['documentTypes'],
                'requirement_type' => $options['requirementTypes'],
                'attachment_category' => $options['attachmentCategories'],
                'severity' => $options['severities'],
                'feature_type' => $options['featureTypes'],
                'channel' => $options['channels'],
                'from_role', 'to_role', 'notify_role' => $options['roles'],
                default => null,
            };
        };
        $fieldLabel = fn (string $field) => str($field)->replace('_id', '')->replace('_', ' ')->title();
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Administration Configuration</p>
            <h1>{{ $config['title'] }}</h1>
            <p>{{ $config['description'] }}</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="session-alert session-alert-error" role="alert">
            Please review the highlighted configuration fields and try again.
        </div>
    @endif

    <nav class="settings-tabbar rule-config-tabs" aria-label="Admin configuration sections">
        @foreach ($settingsLinks as $link)
            <a href="{{ route($link['route']) }}" class="{{ $link['active'] ? 'is-active' : '' }}">{{ $link['label'] }}</a>
        @endforeach
    </nav>

    <section class="summary-card-grid rule-config-summary" aria-label="{{ $config['title'] }} summary">
        <x-dashboard.stat-card label="Total Rules" :value="$summary['total']" accent="navy" />
        <x-dashboard.stat-card label="Active" :value="$summary['active']" accent="green" />
        <x-dashboard.stat-card label="Inactive" :value="$summary['inactive']" accent="gold" />
    </section>

    <section class="rule-config-layout">
        <article class="table-panel rule-config-form-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Create</p>
                    <h2>New {{ str($config['singular'])->title() }}</h2>
                    <p>These settings are rule storage only. No AI provider is called from this page.</p>
                </div>
            </div>

            <form method="POST" action="{{ route($config['route_prefix'] . '.store') }}" class="rule-config-form">
                @csrf

                @foreach ($config['fields'] as $field)
                    <div class="rule-config-field {{ $isTextArea($field) ? 'is-wide' : '' }}">
                        @if ($isBoolean($field))
                            <label class="rule-config-check">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked($checkedValue(null, $field, $field === 'is_active' || $field === 'is_required'))>
                                <span>{{ $fieldLabel($field) }}</span>
                            </label>
                        @elseif ($field === 'from_office_id' || $field === 'to_office_id' || $field === 'notify_office_id')
                            <label for="create-{{ $field }}">{{ $fieldLabel($field) }}</label>
                            <select id="create-{{ $field }}" name="{{ $field }}">
                                <option value="">Any office</option>
                                @foreach ($options['offices'] as $office)
                                    <option value="{{ $office->id }}" @selected((string) old($field) === (string) $office->id)>{{ $office->name }}</option>
                                @endforeach
                            </select>
                        @elseif ($choices = $selectOptions($field))
                            <label for="create-{{ $field }}">{{ $fieldLabel($field) }}</label>
                            <select id="create-{{ $field }}" name="{{ $field }}">
                                @if (! in_array($field, ['requirement_type', 'severity', 'feature_type', 'channel'], true))
                                    <option value="">Any</option>
                                @endif
                                @foreach ($choices as $value => $label)
                                    <option value="{{ $value }}" @selected(old($field) === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif ($isTextArea($field))
                            <label for="create-{{ $field }}">{{ $fieldLabel($field) }}</label>
                            <textarea id="create-{{ $field }}" name="{{ $field }}" rows="{{ $field === 'expected_output_schema' ? 6 : 4 }}">{{ old($field) }}</textarea>
                        @else
                            <label for="create-{{ $field }}">{{ $fieldLabel($field) }}</label>
                            <input id="create-{{ $field }}" name="{{ $field }}" type="{{ str_contains($field, 'days') || $field === 'sort_order' ? 'number' : 'text' }}" value="{{ old($field) }}">
                        @endif

                        @error($field)
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach

                <div class="rule-config-actions">
                    <button type="submit">Create {{ str($config['singular'])->title() }}</button>
                </div>
            </form>
        </article>

        <article class="table-panel rule-config-table-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Registry</p>
                    <h2>{{ $config['title'] }}</h2>
                    <p>Search, edit, activate/deactivate, or remove configuration rules.</p>
                </div>
            </div>

            <form method="GET" action="{{ route($config['route_prefix'] . '.index') }}" class="rule-config-search">
                <label for="rule-search">Search rules</label>
                <input id="rule-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Rule name, document type, status, or template key">
                <div class="filter-actions">
                    <button type="submit">Apply</button>
                    <a href="{{ route($config['route_prefix'] . '.index') }}">Clear</a>
                </div>
            </form>

            <div class="table-scroll">
                <table class="rule-config-table">
                    <thead>
                        <tr>
                            @foreach ($config['fields'] as $field)
                                <th>{{ $fieldLabel($field) }}</th>
                            @endforeach
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                @foreach ($config['fields'] as $field)
                                    <td class="{{ $isTextArea($field) ? 'rule-config-long-cell' : '' }}">
                                        @if ($field === 'is_active')
                                            <span class="status-badge {{ $record->is_active ? 'status-active' : 'status-inactive' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span>
                                        @elseif ($isBoolean($field))
                                            {{ $displayValue($record, $field) }}
                                        @else
                                            <span>{{ $displayValue($record, $field) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <span class="status-badge {{ $record->is_active ? 'status-active' : 'status-inactive' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    <details class="rule-config-edit">
                                        <summary>Edit</summary>
                                        <form method="POST" action="{{ route($config['route_prefix'] . '.update', $record) }}" class="rule-config-form rule-config-form--compact">
                                            @csrf
                                            @method('PATCH')

                                            @foreach ($config['fields'] as $field)
                                                <div class="rule-config-field {{ $isTextArea($field) ? 'is-wide' : '' }}">
                                                    @if ($isBoolean($field))
                                                        <label class="rule-config-check">
                                                            <input type="hidden" name="{{ $field }}" value="0">
                                                            <input type="checkbox" name="{{ $field }}" value="1" @checked($checkedValue($record, $field))>
                                                            <span>{{ $fieldLabel($field) }}</span>
                                                        </label>
                                                    @elseif ($field === 'from_office_id' || $field === 'to_office_id' || $field === 'notify_office_id')
                                                        <label for="edit-{{ $field }}-{{ $record->id }}">{{ $fieldLabel($field) }}</label>
                                                        <select id="edit-{{ $field }}-{{ $record->id }}" name="{{ $field }}">
                                                            <option value="">Any office</option>
                                                            @foreach ($options['offices'] as $office)
                                                                <option value="{{ $office->id }}" @selected((string) $fieldValue($record, $field) === (string) $office->id)>{{ $office->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($choices = $selectOptions($field))
                                                        <label for="edit-{{ $field }}-{{ $record->id }}">{{ $fieldLabel($field) }}</label>
                                                        <select id="edit-{{ $field }}-{{ $record->id }}" name="{{ $field }}">
                                                            @if (! in_array($field, ['requirement_type', 'severity', 'feature_type', 'channel'], true))
                                                                <option value="">Any</option>
                                                            @endif
                                                            @foreach ($choices as $value => $label)
                                                                <option value="{{ $value }}" @selected((string) $fieldValue($record, $field) === (string) $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($isTextArea($field))
                                                        <label for="edit-{{ $field }}-{{ $record->id }}">{{ $fieldLabel($field) }}</label>
                                                        <textarea id="edit-{{ $field }}-{{ $record->id }}" name="{{ $field }}" rows="{{ $field === 'expected_output_schema' ? 6 : 3 }}">{{ $fieldValue($record, $field) }}</textarea>
                                                    @else
                                                        <label for="edit-{{ $field }}-{{ $record->id }}">{{ $fieldLabel($field) }}</label>
                                                        <input id="edit-{{ $field }}-{{ $record->id }}" name="{{ $field }}" type="{{ str_contains($field, 'days') || $field === 'sort_order' ? 'number' : 'text' }}" value="{{ $fieldValue($record, $field) }}">
                                                    @endif
                                                </div>
                                            @endforeach

                                            <div class="rule-config-actions">
                                                <button type="submit">Save Changes</button>
                                            </div>
                                        </form>

                                        <form method="POST" action="{{ route($config['route_prefix'] . '.destroy', $record) }}" class="rule-config-delete-form" onsubmit="return confirm('Delete this configuration rule?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-action icon-action--danger" aria-label="Delete configuration rule" title="Delete configuration rule">
                                                <x-papertrail.icon name="delete" />
                                            </button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($config['fields']) + 2 }}">
                                    <div class="empty-state">
                                        <strong>No configuration rules found</strong>
                                        <p>Create a rule or run the default configuration seeder.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $records->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        </article>
    </section>
@endsection
