<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate(['workspace_id' => ['required', 'integer']]);
        $account = $request->user()->loginAccount();
        $workspace = $account->availableWorkspaces()->firstWhere('id', (int) $validated['workspace_id']);
        abort_unless($workspace, 403);

        AuditLogger::log('Accounts', 'workspace_switched', 'User selected an assigned workspace.', $workspace, null, null, 'info', [
            'login_account_id' => $account->id,
            'previous_workspace_id' => $request->session()->get('active_workspace_id', $account->id),
            'workspace_id' => $workspace->id,
        ]);
        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->forget('last_authenticated_url');
        // Reject stale forms in another tab rather than submitting under a different role.
        $request->session()->regenerate();

        return redirect()->route($workspace->dashboardRoute());
    }
}
