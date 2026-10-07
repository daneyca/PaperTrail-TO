<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ElectronicSignature;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileSignatureController extends Controller
{
    private const DISK = 'local';
    private const BASE_PATH = 'private/signatures';

    public function edit(Request $request): View
    {
        $user = $request->user()->loadMissing(['assignedRole', 'assignedOffice']);

        AuditLogger::signature('signature_profile_viewed', $user, [
            'description' => 'User opened e-signature profile setup.',
        ]);

        return view('profile.signature', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'typed_signature_name' => ['required', 'string', 'max:150'],
            'signer_position' => ['required', 'string', 'max:150'],
            'signature_style' => ['nullable', Rule::in(['drawn', 'uploaded', 'typed'])],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'drawn_signature_data' => ['nullable', 'string'],
        ]);

        $oldValues = $user->only([
            'typed_signature_name',
            'signer_position',
            'signature_image_path',
            'signature_style',
            'signature_setup_completed_at',
        ]);

        $updates = [
            'typed_signature_name' => trim($validated['typed_signature_name']),
            'signer_position' => trim($validated['signer_position']),
            'signature_style' => $this->resolvedStyle($user, $validated['signature_style'] ?? null),
            'signature_setup_completed_at' => now(),
        ];

        if (filled($validated['drawn_signature_data'] ?? null)) {
            $imagePath = $this->storeDrawnSignature($user, $validated['drawn_signature_data']);

            if (! $imagePath) {
                return back()
                    ->withErrors(['drawn_signature_data' => 'The drawn signature could not be saved. Please clear the canvas and try again.'])
                    ->withInput();
            }

            $this->deleteExistingSignatureImage($user);
            $updates['signature_image_path'] = $imagePath;
            $updates['signature_style'] = 'drawn';
        } elseif ($request->hasFile('signature_image')) {
            $this->deleteExistingSignatureImage($user);
            $updates['signature_image_path'] = $request->file('signature_image')
                ->store($this->userSignatureDirectory($user), self::DISK);
            $updates['signature_style'] = 'uploaded';
        } elseif (($validated['signature_style'] ?? null) === 'typed') {
            $updates['signature_style'] = 'typed';
        }

        $user->update($updates);

        AuditLogger::signature('signature_profile_updated', $user, [
            'description' => 'User updated own e-signature profile.',
            'old_values' => $oldValues,
            'new_values' => $user->fresh()->only([
                'typed_signature_name',
                'signer_position',
                'signature_image_path',
                'signature_style',
                'signature_setup_completed_at',
            ]),
        ]);

        return redirect()
            ->route('profile.show')
            ->with('status', 'E-signature profile saved successfully.');
    }

    public function destroyImage(Request $request): RedirectResponse
    {
        $user = $request->user();

        $oldPath = $user->signature_image_path;
        $this->deleteExistingSignatureImage($user);

        $user->update([
            'signature_image_path' => null,
            'signature_style' => 'typed',
        ]);

        AuditLogger::signature('signature_image_removed', $user, [
            'description' => 'User removed own signature image.',
            'old_values' => ['signature_image_path' => $oldPath],
            'new_values' => ['signature_image_path' => null, 'signature_style' => 'typed'],
        ]);

        return redirect()
            ->route('profile.signature.edit')
            ->with('status', 'Signature image removed. Typed signature will be used as fallback.');
    }

    public function showImage(Request $request, User $user): BinaryFileResponse
    {
        if (! $this->canViewSignatureImage($request->user(), $user)) {
            AuditLogger::signature('unauthorized_signature_attempt', $user, [
                'description' => 'Unauthorized signature image access attempt.',
                'severity' => 'warning',
            ]);

            abort(403);
        }

        if (! $user->signature_image_path || ! Storage::disk(self::DISK)->exists($user->signature_image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk(self::DISK)->path($user->signature_image_path), [
            'Content-Type' => $this->contentTypeFor($user->signature_image_path),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function storeDrawnSignature(User $user, string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }

        $encoded = substr($dataUrl, strlen('data:image/png;base64,'));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) > 2 * 1024 * 1024 || strlen($decoded) < 100) {
            return null;
        }

        $path = $this->userSignatureDirectory($user) . '/signature-' . now()->format('YmdHis') . '.png';

        try {
            Storage::disk(self::DISK)->put($path, $decoded);

            return $path;
        } catch (\Throwable $exception) {
            Log::error('PaperTrail drawn signature save failed.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function userSignatureDirectory(User $user): string
    {
        return self::BASE_PATH . '/' . $user->id;
    }

    private function deleteExistingSignatureImage(User $user): void
    {
        if ($user->signature_image_path) {
            $isSignedSnapshotImage = ElectronicSignature::query()
                ->where('signature_image_path', $user->signature_image_path)
                ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
                ->exists();

            if ($isSignedSnapshotImage) {
                return;
            }

            Storage::disk(self::DISK)->delete($user->signature_image_path);
        }
    }

    private function resolvedStyle(User $user, ?string $requestedStyle): string
    {
        if ($requestedStyle) {
            return $requestedStyle;
        }

        return $user->signature_image_path ? ($user->signature_style ?: 'uploaded') : 'typed';
    }

    private function canViewSignatureImage(?User $viewer, User $owner): bool
    {
        if (! $viewer) {
            return false;
        }

        return $viewer->id === $owner->id
            || $viewer->isAdmin()
            || $viewer->hasPermission('documents.view.assigned')
            || $viewer->hasPermission('documents.view.own')
            || $viewer->hasPermission('bac.resolution.view');
    }

    private function contentTypeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
