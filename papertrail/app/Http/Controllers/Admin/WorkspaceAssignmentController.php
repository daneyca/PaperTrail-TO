<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PersonWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceAssignmentController extends Controller
{
    public function store(Request $request, User $user, PersonWorkspaceService $service): RedirectResponse
    {
        if ($user->isOfficeDocumentPreparer()) {
            return back()->with('error', 'Procurement Staff accounts cannot be linked to BAC or other workspaces.');
        }

        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:users,id'],
            'same_person' => ['accepted'],
        ]);
        $workspace = User::findOrFail($data['workspace_id']);

        if ($workspace->isOfficeDocumentPreparer()) {
            return back()->with('error', 'Procurement Staff accounts cannot be linked to BAC or other workspaces.');
        }

        $service->link($user, $workspace);

        return back()->with('status', 'Workspace linked. Use the main office login to access both roles.');
    }

    public function status(Request $request, User $user, User $workspace): RedirectResponse
    {
        abort_unless(! $user->principal_user_id && (int) $workspace->principal_user_id === (int) $user->id, 404);
        $data = $request->validate(['status' => ['required', 'in:active,inactive']]);
        $old = $workspace->only('status');
        $workspace->update(['status' => $data['status']]);
        AuditLogger::log('Accounts', 'workspace_status_changed', 'Administrator changed role workspace access.', $workspace, $old, $workspace->only('status'), 'warning');

        return back()->with('status', 'Workspace access updated. Existing documents and signature history are kept.');
    }
}
