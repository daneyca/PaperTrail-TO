@extends('layouts.dashboard')

@section('title', 'Upload ' . $typeMeta['label'] . ' Template | PaperTrail')

@section('content')
    <div class="space-y-5 papertrail-form-stack document-template-import-page">
        <section class="papertrail-form-hero rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wide text-blue-700">Editable Template Import</p>
            <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-slate-950">Upload Filled {{ $typeMeta['label'] }} Template</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Upload a completed {{ strtoupper($typeMeta['format']) }} worksheet/template for safe review.
                        Files are stored outside the public web root and are not treated as final documents.
                    </p>
                </div>
                <div class="table-actions">
                    <x-ui.action-button
                        :href="route('document-templates.download', ['documentType' => str_replace('_', '-', $type)])"
                        icon="download"
                        label="Download Current Template"
                        tooltip="Download Current Template"
                        variant="download"
                    />
                </div>
            </div>
        </section>

        <section class="papertrail-admin-split papertrail-admin-split--side grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <form method="POST" action="{{ route('document-template-imports.upload', ['documentType' => str_replace('_', '-', $type)]) }}" enctype="multipart/form-data" class="papertrail-upload-card rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    Import parsing is not configured yet. Uploaded templates are recorded as draft-import files for manual review and will not submit, approve, or route a document.
                </div>

                <div class="mt-5 space-y-4">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Filled Template File</span>
                        <input type="file" name="filled_template" accept=".{{ $typeMeta['format'] }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800">
                        <span class="mt-2 block text-xs font-semibold text-slate-500">
                            Allowed: .{{ $typeMeta['format'] }} only. Maximum size: 10 MB. Macro-enabled files are rejected.
                        </span>
                        @error('filled_template')
                            <span class="mt-2 block text-sm font-bold text-red-700">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Notes</span>
                        <textarea name="notes" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800" placeholder="Optional notes for manual review">{{ old('notes') }}</textarea>
                        @error('notes')
                            <span class="mt-2 block text-sm font-bold text-red-700">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rounded-xl bg-blue-950 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-blue-900">
                        Upload Filled Template
                    </button>
                    <a href="{{ url()->previous() }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-800 no-underline hover:bg-slate-50">
                        Back
                    </a>
                </div>
            </form>

            <aside class="papertrail-side-card rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-950">Recent Uploads</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($recentImports as $import)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="truncate text-sm font-black text-slate-900">{{ $import->original_name }}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $import->import_status) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $import->created_at?->format('M d, Y h:i A') }}</p>
                        </div>
                    @empty
                        <div class="empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                            <strong>No uploaded templates yet</strong>
                            <p>Filled template imports will appear here after upload.</p>
                        </div>
                    @endforelse
                </div>
            </aside>
        </section>
    </div>
@endsection
