<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\BacsecMpdoWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function mpdo(Request $request, string $section, BacsecMpdoWorkspaceService $service): RedirectResponse
    {
        $account = $request->user()->loginAccount();
        abort_unless($service->allows($account), 403);
        abort_unless($section === 'bac-secretariat' || isset(BacsecMpdoWorkspaceService::DESTINATIONS[$section]), 404);
        try {
            $workspace = $section === 'bac-secretariat' ? $account : $service->ensure($account);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }
        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->forget('last_authenticated_url');
        $request->session()->regenerate();
        AuditLogger::log('Accounts', 'workspace_switched', 'BACSEC-003 selected the MPDO preparation or BAC Secretariat workspace.', $workspace, null, null, 'info', [
            'login_account_id' => $account->id, 'workspace_id' => $workspace->id,
        ]);

        return redirect()->route($section === 'bac-secretariat' ? 'bac-secretariat.dashboard' : BacsecMpdoWorkspaceService::DESTINATIONS[$section]);
    }

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
