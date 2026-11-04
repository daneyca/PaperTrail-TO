<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AnnualInvestmentProgramDraft;
use App\Services\AnnualInvestmentProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnualInvestmentProgramDraftController extends Controller
{
    public function store(Request $request, AnnualInvestmentProgramService $aip): JsonResponse
    {
        $this->authorizeEditor($request);
        $request->validate(['revision' => ['required', 'integer', 'min:0'], 'draft' => ['required', 'array']]);
        $payload = $aip->validateDraft($request->input('draft'));
        $expectedRevision = (int) $request->input('revision');

        $draft = DB::transaction(function () use ($request, $payload, $expectedRevision) {
            $draft = AnnualInvestmentProgramDraft::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (($draft?->revision ?? 0) !== $expectedRevision) {
                return null;
            }

            if ($draft) {
                $draft->update(['payload' => $payload, 'revision' => $draft->revision + 1]);
            } else {
                $draft = AnnualInvestmentProgramDraft::create([
                    'user_id' => $request->user()->id,
                    'payload' => $payload,
                    'revision' => 1,
                ]);
            }

            return $draft;
        });

        if (! $draft) {
            return response()->json(['message' => 'This draft changed in another tab. Reload the page to see the newer version.'], 409);
        }

        return response()->json([
            'revision' => $draft->revision,
            'saved_at' => $draft->updated_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        $deleted = DB::transaction(function () use ($request) {
            $draft = AnnualInvestmentProgramDraft::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (($draft?->revision ?? 0) !== (int) $request->input('revision')) {
                return false;
            }

            $draft?->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json(['message' => 'This draft changed in another tab. Reload before starting a new AIP.'], 409);
        }

        return response()->json(['ok' => true]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);
    }
}
