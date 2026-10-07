<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminOfficeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Office::query()->withCount('users');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.offices.index', [
            'offices' => $query
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'types' => $this->types(),
            'filters' => $request->only(['search', 'type', 'status']),
            'summary' => [
                'total' => Office::count(),
                'active' => Office::where('status', Office::STATUS_ACTIVE)->count(),
                'endUser' => Office::where('type', Office::TYPE_END_USER)->count(),
                'review' => Office::whereIn('type', [Office::TYPE_FINANCE, Office::TYPE_PROCUREMENT])->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.offices.create', [
            'office' => new Office(['status' => Office::STATUS_ACTIVE]),
            'types' => $this->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_requesting_office' => $request->boolean('is_requesting_office')]);
        $validated = $request->validate($this->rules());
        $validated['code'] = strtoupper($validated['code']);

        $office = Office::create($validated);
        AuditLogger::log('Offices Management', 'office_created', 'Office created.', $office, null, $office->only(['code', 'name', 'type', 'status']));

        return redirect()
            ->route('admin.offices.index')
            ->with('status', 'Office created successfully.');
    }

    public function edit(Office $office): View
    {
        return view('admin.offices.edit', [
            'office' => $office,
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $request->merge(['is_requesting_office' => $request->boolean('is_requesting_office')]);
        $validated = $request->validate($this->rules($office));
        $validated['code'] = strtoupper($validated['code']);

        $oldValues = $office->only(['code', 'name', 'type', 'description', 'status', 'is_requesting_office']);
        $office->update($validated);
        AuditLogger::log('Offices Management', 'office_updated', 'Office updated.', $office, $oldValues, $office->only(['code', 'name', 'type', 'description', 'status', 'is_requesting_office']));

        return redirect()
            ->route('admin.offices.index')
            ->with('status', 'Office updated successfully.');
    }

    public function updateStatus(Office $office): RedirectResponse
    {
        $office->loadCount('users');
        $oldValues = $office->only(['status']);
        $office->update([
            'status' => $office->status === Office::STATUS_ACTIVE
                ? Office::STATUS_INACTIVE
                : Office::STATUS_ACTIVE,
        ]);
        AuditLogger::log('Offices Management', $office->status === Office::STATUS_ACTIVE ? 'Office Activated' : 'Office Deactivated', 'Office status changed.', $office, $oldValues, $office->only(['status']), 'warning', [
            'assigned_users' => $office->users_count,
        ]);

        $message = $office->status === Office::STATUS_ACTIVE
            ? 'Office activated successfully.'
            : 'Office deactivated successfully.';

        if ($office->status === Office::STATUS_INACTIVE && $office->users_count > 0) {
            $message .= ' Existing assigned users remain visible and assigned.';
        }

        return back()->with('status', $message);
    }

    public function show(Office $office): View
    {
        return view('admin.offices.show', [
            'office' => $office->load(['users' => fn ($query) => $query->orderBy('name')]),
        ]);
    }

    private function rules(?Office $office = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('offices', 'code')->ignore($office),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('offices', 'name')->ignore($office),
            ],
            'type' => ['required', Rule::in($this->types())],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Office::STATUS_ACTIVE, Office::STATUS_INACTIVE])],
            'is_requesting_office' => ['required', 'boolean'],
        ];
    }

    private function types(): array
    {
        return [
            Office::TYPE_SYSTEM,
            Office::TYPE_END_USER,
            Office::TYPE_PROCUREMENT,
            Office::TYPE_FINANCE,
            Office::TYPE_APPROVING,
            Office::TYPE_LEGISLATIVE,
        ];
    }
}
