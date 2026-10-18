<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SupplierSignatureRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompetitiveBiddingSignatureDocumentController extends Controller
{
    private const DOCUMENT_TYPE = 'competitive_bidding_document';

    private const PRE_BID_MEMBER_SLOTS = ['member_1', 'member_2', 'member_3'];

    private const ROLE_OPTIONS = [
        'bac_secretariat' => ['label' => 'BAC Secretariat', 'role' => User::ROLE_BAC_SECRETARIAT, 'type' => 'internal'],
        'twg' => ['label' => 'Technical Working Group', 'role' => User::ROLE_BAC_MEMBER, 'type' => 'internal'],
        'bac_chairperson' => ['label' => 'BAC Chairperson', 'role' => User::ROLE_BAC_CHAIR, 'type' => 'internal'],
        'bac_vice_chairperson' => ['label' => 'BAC Vice-Chairperson', 'role' => User::ROLE_BAC_VICE_CHAIRPERSON, 'type' => 'internal'],
        'bac_member' => ['label' => 'BAC Member', 'role' => User::ROLE_BAC_MEMBER, 'type' => 'internal'],
        'hope' => ['label' => 'Municipal Mayor / HOPE', 'role' => User::ROLE_APPROVING_AUTHORITY, 'type' => 'internal'],
        'supplier' => ['label' => 'Supplier / Contractor', 'role' => null, 'type' => 'supplier'],
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_key' => ['required', 'string', 'max:120'],
            'document_title' => ['required', 'string', 'max:255'],
            'tracking_number' => ['required', 'string', 'max:120', 'exists:competitive_bidding_records,reference_number'],
            'document_html' => ['required', 'string', 'max:2000000'],
            'signatories' => ['required', 'string', 'max:200000'],
        ]);

        if ($validated['template_key'] === 'pre_bid_conference_minutes') {
            abort_unless($request->user()?->user_id === 'BACSEC-002', 403);
            $validated['document_title'] = 'Minutes of the Pre-Bid Conference';
        }

        $signatories = json_decode($validated['signatories'], true);

        if (! is_array($signatories)) {
            throw ValidationException::withMessages([
                'signatories' => 'The signatory routing list could not be read. Please refresh the page and try again.',
            ]);
        }

        $signatories = $this->normalizeSignatories($signatories);

        if ($signatories === []) {
            throw ValidationException::withMessages([
                'signatories' => 'Add at least one signatory name and role before forwarding the document.',
            ]);
        }

        $preBidMemberRecipients = $validated['template_key'] === 'pre_bid_conference_minutes'
            ? $this->preBidMemberRecipients($signatories)
            : [];

        [$document, $internalCount, $supplierCount] = DB::transaction(function () use ($request, $validated, $signatories, $preBidMemberRecipients) {
            $document = CompetitiveBiddingSignatureDocument::create([
                'template_key' => $validated['template_key'],
                'document_title' => $validated['document_title'],
                'tracking_number' => $validated['tracking_number'],
                'document_html' => $this->cleanDocumentHtml($validated['document_html']),
                'signatories' => $signatories,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
            ]);

            $internalCount = 0;
            $supplierCount = 0;

            foreach ($signatories as $index => $signatory) {
                $role = self::ROLE_OPTIONS[$signatory['role_key']] ?? null;

                if (! $role) {
                    continue;
                }

                if ($role['type'] === 'supplier') {
                    SupplierSignatureRequest::create([
                        'document_type' => $document->template_key,
                        'document_title' => $document->document_title,
                        'tracking_number' => $document->displayNumber(),
                        'supplier_name' => $signatory['supplier_name'] ?: ($signatory['name'] ?: 'Supplier / Contractor'),
                        'signatory_name' => $signatory['name'] ?: null,
                        'signatory_position' => $signatory['role_label'],
                        'requested_by_user_id' => $request->user()->id,
                        'requested_at' => now(),
                        'status' => SupplierSignatureRequest::STATUS_PENDING,
                        'instructions' => 'Please review, sign, and upload the signed copy of this competitive bidding document.',
                        'metadata' => [
                            'competitive_bidding_signature_document_id' => $document->id,
                            'signatory_slot' => $signatory['slot'],
                            'template_key' => $document->template_key,
                        ],
                    ]);

                    $supplierCount++;
                    continue;
                }

                $recipientId = null;

                if ($document->template_key === 'pre_bid_conference_minutes') {
                    $recipientId = $signatory['slot'] === 'prepared_by'
                        ? $request->user()->id
                        : ($preBidMemberRecipients[$signatory['slot']] ?? null);
                }

                SignatureRequest::create([
                    'request_uuid' => (string) Str::uuid(),
                    'document_type' => self::DOCUMENT_TYPE,
                    'document_id' => $document->id,
                    'document_label' => $document->document_title,
                    'tracking_number' => $document->displayNumber(),
                    'signatory_slot' => $signatory['slot'],
                    'signatory_label' => $signatory['role_label'],
                    'signing_order' => $index + 1,
                    'signing_mode' => 'parallel',
                    'is_required' => true,
                    'requested_by_user_id' => $request->user()->id,
                    'requested_to_user_id' => $recipientId,
                    'requested_to_role' => $role['role'],
                    'status' => SignatureRequest::STATUS_PENDING,
                    'metadata' => [
                        'printed_name' => $signatory['name'],
                        'display_role_label' => $signatory['role_label'],
                        'role_key' => $signatory['role_key'],
                        'template_key' => $document->template_key,
                    ],
                ]);

                $internalCount++;
            }

            AuditLogger::signature('competitive_bidding_signature_routed', $document, [
                'description' => 'Competitive bidding document routed for signatures.',
                'metadata' => [
                    'internal_signature_requests' => $internalCount,
                    'supplier_signature_requests' => $supplierCount,
                    'template_key' => $document->template_key,
                ],
            ]);

            return [$document, $internalCount, $supplierCount];
        });

        $message = "Forwarded {$document->document_title}: {$internalCount} internal e-signature request(s)";
        $message .= $supplierCount > 0 ? " and {$supplierCount} supplier signing link(s)." : '.';

        if ($supplierCount > 0) {
            $message .= ' Open Supplier Signatures to copy the external supplier link.';
        }

        return back()->with('status', $message);
    }

    private function preBidMemberRecipients(array $signatories): array
    {
        $recipients = [];

        foreach ($signatories as $signatory) {
            if ($signatory['role_key'] === 'bac_member'
                && ! in_array($signatory['slot'], self::PRE_BID_MEMBER_SLOTS, true)) {
                throw ValidationException::withMessages([
                    'signatories' => 'Only the three BAC Member signatories can sign these minutes.',
                ]);
            }
        }

        foreach (self::PRE_BID_MEMBER_SLOTS as $slot) {
            $matches = array_values(array_filter($signatories, fn (array $signatory) => $signatory['slot'] === $slot));

            if (count($matches) !== 1
                || $matches[0]['role_key'] !== 'bac_member'
                || ! $matches[0]['recipient_user_id']) {
                throw ValidationException::withMessages([
                    'signatories' => 'Select an account for each BAC Member before forwarding.',
                ]);
            }

            $user = User::query()->find($matches[0]['recipient_user_id']);

            if (! $user || ! $user->isActive() || ! $user->hasRole(User::ROLE_BAC_MEMBER)) {
                throw ValidationException::withMessages([
                    'signatories' => 'Each selected signer must have an active BAC Member account.',
                ]);
            }

            if (in_array($user->id, $recipients, true)) {
                throw ValidationException::withMessages([
                    'signatories' => 'Select a different account for each BAC Member.',
                ]);
            }

            $recipients[$slot] = $user->id;
        }

        return $recipients;
    }

    private function normalizeSignatories(array $signatories): array
    {
        return collect($signatories)
            ->filter(fn ($signatory) => is_array($signatory))
            ->map(function (array $signatory, int $index): ?array {
                $roleKey = (string) ($signatory['role_key'] ?? '');

                if (! array_key_exists($roleKey, self::ROLE_OPTIONS)) {
                    return null;
                }

                $name = trim(strip_tags((string) ($signatory['name'] ?? '')));
                $supplierName = trim(strip_tags((string) ($signatory['supplier_name'] ?? '')));

                if ($name === '' && $supplierName === '' && $roleKey !== 'supplier') {
                    return null;
                }

                return [
                    'slot' => Str::slug((string) ($signatory['slot'] ?? "signatory-{$index}"), '_'),
                    'label' => trim(strip_tags((string) ($signatory['label'] ?? 'Signatory'))) ?: 'Signatory',
                    'name' => $name,
                    'role_key' => $roleKey,
                    'role_label' => self::ROLE_OPTIONS[$roleKey]['label'],
                    'recipient_user_id' => filter_var($signatory['recipient_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
                    'supplier_name' => $supplierName,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function cleanDocumentHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/\scontenteditable=("|\')true\1/i', '', $html) ?? $html;
        $html = preg_replace('/\sdata-cb-[a-z0-9_-]+=("|\')[^"\']*\1/i', '', $html) ?? $html;

        return trim($html);
    }
}
