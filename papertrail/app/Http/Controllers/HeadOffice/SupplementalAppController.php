<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\SupplementalApp;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplementalAppController extends Controller
{
    public function show(Request $request, SupplementalApp $supplementalApp): View
    {
        $this->authorizeOfficeAccess($request->user(), $supplementalApp);

        AuditLogger::log('Supplemental APP', 'Supplemental APP Viewed by End User', 'Head Office viewed a linked Supplemental APP.', $supplementalApp);

        return view('head-office.supplemental-apps.show', [
            'supplementalApp' => $supplementalApp->load($this->relations()),
        ]);
    }

    public function print(Request $request, SupplementalApp $supplementalApp): View
    {
        $this->authorizeOfficeAccess($request->user(), $supplementalApp);

        AuditLogger::log('Supplemental APP', 'Supplemental APP Printed by End User', 'Head Office opened Supplemental APP print view.', $supplementalApp);

        return view('bac-secretariat.supplemental-apps.print', [
            'supplementalApp' => $supplementalApp->load($this->relations()),
            'backUrl' => route('head-office.supplemental-apps.show', $supplementalApp),
        ]);
    }

    private function authorizeOfficeAccess(User $user, SupplementalApp $supplementalApp): void
    {
        if ($user->office_id && (int) $supplementalApp->requesting_office_id === (int) $user->office_id) {
            return;
        }

        if ($supplementalApp->sourcePrDocument
            && ((int) $supplementalApp->sourcePrDocument->submitted_by_user_id === (int) $user->id
                || (int) $supplementalApp->sourcePrDocument->prepared_by_user_id === (int) $user->id)) {
            return;
        }

        AuditLogger::log('Supplemental APP', 'Unauthorized Access Attempt', 'Head Office attempted to access a Supplemental APP outside office scope.', $supplementalApp, null, null, 'warning');
        abort(403);
    }

    private function relations(): array
    {
        return [
            'items.sourcePrItem',
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.submittedBy',
            'requestingOffice',
            'preparedBy',
            'submittedBy',
            'acceptedBy',
        ];
    }
}
