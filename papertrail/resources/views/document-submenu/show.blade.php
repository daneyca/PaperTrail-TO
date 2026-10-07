@extends('layouts.dashboard')

@section('title', $title . ' | PaperTrail')

@section('content')
    @php
        $panelTitle = $panelTitle ?? trim(str_replace(' Submenu', '', $title));
        $showHelpPanel = ! empty($helpSteps ?? []);
    @endphp

    <div class="document-submenu-page space-y-4">
        <x-document-submenu.page-header
            :eyebrow="$eyebrow"
            :title="$title"
            :subtitle="$subtitle"
            :back-route="$backRoute ?? null"
        />

        <x-document-submenu.panel :title="$panelTitle" :cards="$cards" />

        <div class="grid gap-5 {{ $showHelpPanel ? 'xl:grid-cols-[minmax(0,1fr)_340px]' : '' }}">
            <x-documents.records-list
                :title="$recentRecordsTitle ?? 'Recent Document Records'"
                :subtitle="$recentRecordsSubtitle ?? 'Documents created, submitted, or routed through this module will appear here.'"
                :records="$recentRecords ?? []"
                :document-type="$recentRecordsDocumentType ?? null"
                :empty-message="$recentRecordsEmptyMessage ?? 'No document records found.'"
            />
            @if ($showHelpPanel)
                <x-document-submenu.help-panel :steps="$helpSteps" />
            @endif
        </div>
    </div>
@endsection
