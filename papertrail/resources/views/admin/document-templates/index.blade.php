@extends('layouts.dashboard')

@section('title', 'Document Templates | PaperTrail')

@section('content')
    @php
        $currentTemplates = collect($templates->items())->filter(fn ($template) => (bool) $template->is_active)->keyBy('document_type');
    @endphp

    <div class="space-y-5 papertrail-form-stack document-template-admin-page">
        <section class="papertrail-form-hero rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wide text-blue-700">Administration</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950">Document Templates</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Manage editable master files for template downloads. Credentials and storage paths are never shown here.
            </p>
        </section>

        <section class="papertrail-admin-split document-template-layout grid gap-5 xl:grid-cols-[420px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('admin.document-templates.store') }}" enctype="multipart/form-data" class="papertrail-upload-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <input type="hidden" name="is_active" value="1">

                <h2 class="text-lg font-black text-slate-950">Upload Master Template</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    Upload the current official editable template. New uploads replace the active download template for the selected document type.
                </p>

                <div class="template-upload-grid mt-4 space-y-4">
                    <label class="template-upload-field block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Document Type</span>
                        <select name="document_type" required class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($types as $key => $meta)
                                <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $meta['label'] }} (.{{ $meta['format'] }})</option>
                            @endforeach
                        </select>
                        @error('document_type') <span class="mt-1 block text-sm font-bold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <label class="template-upload-field block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Template Name</span>
                        <input type="text" name="template_name" value="{{ old('template_name') }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @error('template_name') <span class="mt-1 block text-sm font-bold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <label class="template-upload-field template-upload-field--full block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Template File</span>
                        <input type="file" name="template_file" accept=".xlsx,.docx" required class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <span class="mt-1 block text-xs text-slate-500">Allowed: .xlsx for spreadsheet templates, .docx for BAC Resolution. Max 10 MB.</span>
                        @error('template_file') <span class="mt-1 block text-sm font-bold text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>

                <button type="submit" class="mt-5 rounded-xl bg-blue-950 px-5 py-3 text-sm font-black text-white hover:bg-blue-900">
                    Upload Master Template
                </button>
            </form>

            <section class="papertrail-template-current rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-blue-700">Active Templates</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Current Official Templates</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Download links point to the active master template used by PaperTrail document preparation.
                    </p>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach ($types as $key => $meta)
                        @php
                            $template = $currentTemplates->get($key);
                        @endphp

                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="text-[0.65rem] font-black uppercase tracking-wide text-slate-500">{{ strtoupper($meta['format']) }}</span>
                                    <h3 class="mt-1 text-sm font-black text-slate-950">{{ $meta['label'] }}</h3>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[0.65rem] font-black uppercase text-emerald-700">Official</span>
                            </div>

                            <p class="mt-3 text-xs font-bold leading-5 text-slate-600">
                                {{ $template?->template_name ?? 'Current active template download' }}
                            </p>

                            @if ($template?->updated_at)
                                <p class="mt-1 text-[0.7rem] font-bold text-slate-500">Updated {{ $template->updated_at->format('M d, Y') }}</p>
                            @endif

                            <div class="mt-3 table-actions">
                                <x-ui.action-button
                                    :href="route('document-templates.download', ['documentType' => str_replace('_', '-', $key)])"
                                    icon="download"
                                    label="Download Current Template"
                                    tooltip="Download Current Template"
                                    variant="download"
                                />
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside id="hide-template-version-history" class="mt-4 rounded-xl border border-dashed border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-wide text-blue-700">Future Enhancement</p>
                    <p class="mt-1 text-sm font-semibold leading-6 text-blue-950">
                        Template version control is planned for future enhancement, including template history, rollback, and version comparison.
                    </p>
                </aside>
            </section>
        </section>
    </div>
@endsection
