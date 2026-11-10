<?php

namespace App\Http\Controllers;

use App\Models\ProcurementDocument;
use App\Services\{AuditLogger, StatusTrackingService};
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusTrackingController extends Controller
{
    public function __construct(private readonly StatusTrackingService $tracking) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return view('status-tracking.index', [
            ...$this->tracking->listing($request->user(), $filters),
            'filters' => $filters,
            'eyebrow' => $request->user()->role,
        ]);
    }

    public function show(Request $request, string $type, int $document): View
    {
        $record = $this->tracking->find($request->user(), $type, $document);
        $payload = $this->tracking->details($record, $type);
        AuditLogger::log('Status Tracking', 'Document Movement Viewed', 'Viewed authorized document status and movement history.', $record);

        return view('status-tracking.show', [
            'document' => $record,
            'tracking' => $payload,
            'eyebrow' => $request->user()->role,
            'isPr' => $record instanceof ProcurementDocument && in_array($record->document_type, ['PR', 'Purchase Request'], true),
            'canShowHierarchy' => $record instanceof ProcurementDocument,
        ]);
    }
}
