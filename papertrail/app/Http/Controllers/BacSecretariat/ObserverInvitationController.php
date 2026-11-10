<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingRecord;
use App\Models\DocumentAttachment;
use App\Models\ObserverInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ObserverInvitationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeEditor($request);

        return view('bac-secretariat.competitive-bidding.observers.index', [
            'invitations' => ObserverInvitation::where('prepared_by_user_id', $request->user()->id)
                ->with('biddingRecord')->withCount('copies')->latest('updated_at')->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeEditor($request);

        return $this->editor(new ObserverInvitation());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $this->validateLetter($request);
        $invitation = ObserverInvitation::create([
            'prepared_by_user_id' => $request->user()->id,
            'competitive_bidding_record_id' => $data['record_id'] ?? null,
            'payload' => $data['letter'],
        ]);
        AuditLogger::log('Observer Invitations', 'Invitation Saved', 'BACSEC-003 saved an observer invitation for physical signing and delivery.', $invitation);

        return redirect()->route('bac-secretariat.competitive-bidding.observers.show', $invitation)
            ->with('observer_draft_key', 'papertrail:observer:'.$request->user()->id.':new:0')
            ->with('status', 'Invitation saved. Print the letter, collect physical signatures or acknowledgment, then upload the copies here.');
    }

    public function show(Request $request, ObserverInvitation $invitation): View
    {
        $this->authorizeEditor($request, $invitation);

        return view('bac-secretariat.competitive-bidding.observers.show', [
            'invitation' => $invitation->load(['copies.uploadedBy', 'biddingRecord']),
        ]);
    }

    public function edit(Request $request, ObserverInvitation $invitation): View
    {
        $this->authorizeEditor($request, $invitation);

        return $this->editor($invitation);
    }

    public function update(Request $request, ObserverInvitation $invitation): RedirectResponse
    {
        $this->authorizeEditor($request, $invitation);
        $data = $this->validateLetter($request);
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $data, $invitation) {
            $current = ObserverInvitation::lockForUpdate()->findOrFail($invitation->id);
            if ($current->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => 'This letter changed in another tab. Reload the latest saved letter before editing.']);
            }
            $current->update([
                'competitive_bidding_record_id' => $data['record_id'] ?? null,
                'payload' => $data['letter'],
                'revision' => $current->revision + 1,
            ]);
            AuditLogger::log('Observer Invitations', 'Invitation Updated', 'BACSEC-003 updated the editable observer invitation.', $current);
        });

        return redirect()->route('bac-secretariat.competitive-bidding.observers.show', $invitation)
            ->with('observer_draft_key', 'papertrail:observer:'.$request->user()->id.':'.$invitation->id.':'.$request->integer('revision'))
            ->with('status', 'Invitation changes saved. Previously uploaded copies are retained with their original letter revision.');
    }

    public function print(Request $request, ObserverInvitation $invitation): View
    {
        $this->authorizeEditor($request, $invitation);

        return view('bac-secretariat.competitive-bidding.observers.print', compact('invitation'));
    }

    public function uploadCopies(Request $request, ObserverInvitation $invitation): RedirectResponse
    {
        $this->authorizeEditor($request, $invitation);
        $data = $request->validate([
            'copies' => ['required', 'array', 'min:1', 'max:10'],
            'copies.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'observer_name' => ['required', 'string', 'max:255'],
            'copy_type' => ['required', 'in:signed_letter,received_copy,acknowledgment'],
            'received_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $invitation, $data, &$storedPaths) {
                $current = ObserverInvitation::lockForUpdate()->findOrFail($invitation->id);
                foreach ($data['copies'] as $file) {
                    $path = $file->store('private/observer-invitations/'.$current->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('The signed copy could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $current->copies()->create([
                        'document_type' => 'observer_invitation', 'document_id' => $current->id,
                        'tracking_number' => $current->biddingRecord?->reference_number,
                        'uploaded_by_user_id' => $request->user()->id,
                        'original_name' => $file->getClientOriginalName(), 'original_filename' => $file->getClientOriginalName(),
                        'stored_filename' => basename($path), 'file_path' => $path, 'disk' => 'local',
                        'mime_type' => $file->getMimeType(), 'file_extension' => strtolower($file->extension()),
                        'size' => $file->getSize(), 'file_size' => $file->getSize(),
                        'description' => $data['notes'] ?? null,
                        'attachment_category' => DocumentAttachment::CATEGORY_SIGNED_DOCUMENT,
                        'ocr_status' => DocumentAttachment::OCR_NOT_REQUIRED,
                        'ai_analysis_status' => DocumentAttachment::AI_SKIPPED,
                        'is_confidential' => true, 'status' => DocumentAttachment::STATUS_ACTIVE,
                        'extracted_metadata' => [
                            'observer_name' => $data['observer_name'], 'copy_type' => $data['copy_type'],
                            'received_on' => $data['received_on'] ?? null,
                            'letter_revision' => $current->revision, 'letter_snapshot' => $current->payload,
                        ],
                    ]);
                }
                AuditLogger::log('Observer Invitations', 'Physical Copies Uploaded', 'BACSEC-003 uploaded signed or received observer invitation copies.', $current);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($storedPaths);
            throw $error;
        }

        $count = count($data['copies']);

        return back()->with('status', $count.' '.($count === 1 ? 'copy' : 'copies').' uploaded successfully.');
    }

    public function viewCopy(Request $request, ObserverInvitation $invitation, DocumentAttachment $attachment)
    {
        $this->authorizeCopy($request, $invitation, $attachment);
        $disk = Storage::disk('local');
        abort_unless($attachment->file_path && $disk->exists($attachment->file_path), 404);
        if ($request->boolean('download')) {
            return $disk->download($attachment->file_path, $attachment->displayName());
        }

        return $disk->response($attachment->file_path, $attachment->displayName(), [
            'Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function removeCopy(Request $request, ObserverInvitation $invitation, DocumentAttachment $attachment): RedirectResponse
    {
        $this->authorizeCopy($request, $invitation, $attachment);
        $attachment->update(['status' => DocumentAttachment::STATUS_DELETED, 'deleted_by_user_id' => $request->user()->id]);
        $attachment->delete();
        AuditLogger::log('Observer Invitations', 'Copy Removed', 'BACSEC-003 removed an incorrect uploaded copy.', $attachment);

        return back()->with('status', 'Uploaded copy removed.');
    }

    private function editor(ObserverInvitation $invitation): View
    {
        return view('bac-secretariat.competitive-bidding.observers.edit', [
            'invitation' => $invitation,
            'data' => $invitation->exists ? $invitation->payload : ObserverInvitation::defaults(),
            'records' => CompetitiveBiddingRecord::orderByDesc('created_at')->get(),
        ]);
    }

    private function authorizeEditor(Request $request, ?ObserverInvitation $invitation = null): void
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);
        if ($invitation) {
            abort_unless((int) $invitation->prepared_by_user_id === (int) $request->user()->id, 403);
        }
    }

    private function authorizeCopy(Request $request, ObserverInvitation $invitation, DocumentAttachment $attachment): void
    {
        $this->authorizeEditor($request, $invitation);
        abort_unless($attachment->attachable_type === ObserverInvitation::class
            && (int) $attachment->attachable_id === (int) $invitation->id
            && $attachment->status === DocumentAttachment::STATUS_ACTIVE, 404);
    }

    private function validateLetter(Request $request): array
    {
        $rules = [
            'record_id' => ['nullable', 'integer', 'exists:competitive_bidding_records,id'],
            'letter' => ['required', 'array'],
            'letter.sections' => ['required', 'array', 'min:1', 'max:8'],
            'letter.sections.*.heading' => ['nullable', 'string', 'max:255'],
            'letter.sections.*.schedules' => ['required', 'array', 'min:1', 'max:30'],
            'letter.sections.*.schedules.*.when' => ['nullable', 'string', 'max:500'],
            'letter.sections.*.schedules.*.projects' => ['required', 'array', 'min:1', 'max:30'],
            'letter.sections.*.schedules.*.projects.*.description' => ['nullable', 'string', 'max:5000'],
            'letter.sections.*.schedules.*.projects.*.budget_prefix' => ['nullable', 'string', 'max:255'],
            'letter.sections.*.schedules.*.projects.*.budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
        foreach (array_keys(ObserverInvitation::defaults()) as $key) {
            if ($key !== 'sections') {
                $rules['letter.'.$key] = ['nullable', 'string', 'max:5000'];
            }
        }

        return $request->validate($rules);
    }
}
