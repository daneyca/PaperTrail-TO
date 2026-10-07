@php
    $paperTrailLogoPath = 'images/logos/papertrail-logo.jpg';
    $hasPaperTrailLogo = file_exists(public_path($paperTrailLogoPath));
@endphp

<section class="dashboard-hero admin-users-hero">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Settings Management</h1>
        <p>Manage PaperTrail configuration through category-based administrative controls.</p>
    </div>
</section>

@if ($errors->any())
    <div class="session-alert session-alert-error" role="alert">
        Please review the highlighted settings and try again.
    </div>
@endif

<section class="settings-console" aria-label="Settings console">
    <aside class="settings-category-nav" aria-label="Settings categories">
        <div class="settings-nav-heading">
            <span>Categories</span>
            <strong>{{ count($groups) }}</strong>
        </div>

        <nav>
            @foreach ($groups as $key => $item)
                @php
                    $href = isset($item['route'])
                        ? route($item['route'])
                        : ($key === 'general' ? route('admin.settings.index') : ($key === 'email' ? route('admin.settings.email') : route('admin.settings.group', $key)));
                    $isActive = $groupKey === $key || (isset($item['route']) && request()->routeIs($item['route']));
                @endphp
                <a href="{{ $href }}" class="{{ $isActive ? 'is-active' : '' }}">
                    <strong>{{ $item['title'] }}</strong>
                    <span>{{ $item['description'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="settings-console-main">
        <header class="settings-console-header">
            <div>
                <p class="eyebrow">{{ str($groupKey)->replace('-', ' ')->title() }}</p>
                <h2>{{ $group['title'] }}</h2>
                <p>{{ $group['description'] }}</p>
            </div>
        </header>

        @if ($groupKey === 'lgu')
            @php
                $logoPath = $settings->firstWhere('key', 'lgu.logo_path')?->value;
            @endphp

            <div class="settings-list-panel settings-logo-panel" aria-label="LGU logo upload">
                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Logo Upload</strong>
                        <p>Upload a municipality or office logo for future report headers and system branding.</p>
                    </div>

                    <div class="settings-row-control settings-logo-control">
                        <div class="settings-logo-preview">
                            @if ($logoPath)
                                <img src="{{ asset('storage/' . $logoPath) }}" alt="Current LGU logo">
                            @elseif ($hasPaperTrailLogo)
                                <img src="{{ asset($paperTrailLogoPath) }}" alt="PaperTrail logo">
                            @else
                                <span>PT</span>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data" class="logo-upload-form">
                            @csrf
                            <label for="logo">Upload logo</label>
                            <input id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                            @error('logo')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                            <button type="submit">Upload</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update', $groupKey) }}" class="settings-list-panel">
            @csrf
            @method('PATCH')

            @forelse ($settings as $setting)
                @php
                    $field = str_replace('.', '_', $setting->key);
                @endphp

                <div class="settings-row {{ $setting->is_locked ? 'is-locked' : '' }}">
                    <div class="settings-row-copy">
                        <label for="{{ $field }}">
                            {{ $setting->label }}
                            @if ($setting->is_locked)
                                <span>Locked</span>
                            @endif
                        </label>

                        @if ($setting->description)
                            <p>{{ $setting->description }}</p>
                        @endif
                    </div>

                    <div class="settings-row-control">
                        @if ($setting->type === 'textarea')
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="3" @disabled($setting->is_locked)>{{ old($field, $setting->value) }}</textarea>
                        @elseif ($setting->type === 'boolean')
                            <select id="{{ $field }}" name="{{ $field }}" @disabled($setting->is_locked)>
                                <option value="1" @selected(old($field, $setting->value) == '1')>Enabled</option>
                                <option value="0" @selected(old($field, $setting->value) == '0')>Disabled</option>
                            </select>
                        @elseif ($setting->type === 'select')
                            <select id="{{ $field }}" name="{{ $field }}" @disabled($setting->is_locked)>
                                @foreach ($setting->options ?? [] as $option)
                                    <option value="{{ $option['value'] }}" @selected(old($field, $setting->value) == $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ in_array($setting->type, ['email', 'number'], true) ? $setting->type : 'text' }}" value="{{ old($field, $setting->value) }}" @disabled($setting->is_locked)>
                        @endif

                        @error($field)
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <strong>No settings found</strong>
                    <p>Run the database seeder to create the default PaperTrail settings.</p>
                </div>
            @endforelse

            @if ($settings->isNotEmpty())
                <div class="settings-console-actions">
                    <button type="submit">Save Changes</button>
                    <a href="{{ route('admin.settings.index') }}">Reset View</a>
                </div>
            @endif
        </form>
    </div>
</section>
