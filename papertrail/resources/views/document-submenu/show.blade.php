@extends('layouts.dashboard')

@section('title', $title . ' | PaperTrail')

@section('content')
    @php
        $panelTitle = $panelTitle ?? trim(str_replace(' Submenu', '', $title));
        $showActionsPanel = $showActionsPanel ?? true;
    @endphp

    <div class="document-submenu-page space-y-4">
        <x-document-submenu.page-header
            :eyebrow="$eyebrow"
            :title="$title"
            :back-route="$backRoute ?? null"
        />

        @if ($showActionsPanel)
            <x-document-submenu.panel :title="$panelTitle" :cards="$cards" />
        @endif

        <div class="grid gap-5">
            <x-documents.records-list
                :title="$recentRecordsTitle ?? 'Recent Document Records'"
                :subtitle="$recentRecordsSubtitle ?? 'Documents created, submitted, or routed through this module will appear here.'"
                :records="$recentRecords ?? []"
                :document-type="$recentRecordsDocumentType ?? null"
                :empty-message="$recentRecordsEmptyMessage ?? 'No document records found.'"
            />
        </div>
    </div>
@endsection
