<?php

namespace App\Http\Controllers;

use App\Models\{Rfq, RfqSupplierQuotation};
use App\Services\{RfqSupplierWorkflowService, SvpChainService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicRfqResponseController extends Controller
{
    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function quotation(string $token): RfqSupplierQuotation
    {
        $quotation = RfqSupplierQuotation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($quotation->status === 'cancelled' || $quotation->expires_at->isPast(), 410, 'This supplier response link has expired or was cancelled. Please contact the requesting office.');
        abort_unless($quotation->rfq?->isControlled(), 410, 'This RFQ is no longer accepting supplier access.');
        return $quotation;
    }

    public function show(string $token)
    {
        $quotation = $this->quotation($token);
        RfqSupplierQuotation::whereKey($quotation->id)->where('status', 'pending')->update(['status' => 'viewed', 'viewed_at' => now()]);
        $quotation->refresh();
        return response()->view('rfq-responses.public', compact('quotation', 'token'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('X-Frame-Options', 'DENY');
    }

    public function submit(Request $request, string $token, RfqSupplierWorkflowService $workflow)
    {
        $quotation = $this->quotation($token);
        abort_unless($quotation->isOpen(), 409, 'This quotation has already been submitted and cannot be changed.');
        $data = $request->validate([
            'supplier_contact' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9() .-]{7,39}$/'],
            'supplier_tin' => ['required', 'string', 'max:40', 'regex:/^[0-9][0-9 -]{5,39}$/'],
            'supplier_address' => ['required', 'string', 'max:1000'],
            'signatory_name' => ['required', 'string', 'max:255'], 'signatory_position' => ['required', 'string', 'max:255'],
            'delivery_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'philgeps_registration_number' => ['required', 'string', 'max:255'],
            'philgeps_registration_type' => ['nullable', 'string', 'max:255'],
            'supplier_remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'size:'.count($quotation->issued_snapshot['items'])],
            'items.*.unit_price' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'signature_data' => ['required', 'string', 'max:300000'], 'signature_consent' => ['accepted'],
        ]);
        $signature = $data['signature_data'];
        $binary = str_starts_with($signature, 'data:image/png;base64,') ? base64_decode(substr($signature, 22), true) : false;
        $size = $binary === false ? false : @getimagesizefromstring($binary);
        if (! $size || $size[2] !== IMAGETYPE_PNG || $size[0] < 20 || $size[1] < 10 || $size[0] > 2000 || $size[1] > 1000) {
            throw ValidationException::withMessages(['signature_data' => 'Draw your signature in the signature box before submitting.']);
        }
        $image = @imagecreatefromstring($binary);
        $ink = 0;
        if ($image) {
            for ($y = 0; $y < $size[1] && $ink < 16; $y += 2) {
                for ($x = 0; $x < $size[0] && $ink < 16; $x += 2) {
                    $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if ($pixel['alpha'] < 120 && min($pixel['red'], $pixel['green'], $pixel['blue']) < 220) {
                        $ink++;
                    }
                }
            }
            imagedestroy($image);
        }
        if ($ink < 16) {
            throw ValidationException::withMessages(['signature_data' => 'The signature box is blank. Draw your signature before submitting.']);
        }
        $phoneDigits = preg_replace('/\D/', '', $data['supplier_contact']);
        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            throw ValidationException::withMessages(['supplier_contact' => 'Enter a valid telephone or mobile number.']);
        }
        DB::transaction(function () use ($request, $quotation, $workflow, $data, $signature) {
            $rfq = Rfq::whereKey($quotation->rfq_id)->lockForUpdate()->firstOrFail();
            $locked = RfqSupplierQuotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isOpen(), 409, 'This link is no longer accepting a response.');
            abort_unless($rfq->isControlled() && $workflow->officerSignatureIsCurrent($rfq), 410, 'The issued RFQ is no longer current. Please contact the requesting office.');
            $items = [];
            $total = 0;
            foreach ($locked->issued_snapshot['items'] as $index => $item) {
                $value = $data['items'][$index]['unit_price'] ?? null;
                if ($value === null) {
                    throw ValidationException::withMessages(['items' => 'Enter a unit price for every listed RFQ item.']);
                }
                $parts = explode('.', (string) $value);
                $priceCents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
                $quantityHundredths = (int) round((float) $item['quantity'] * 100);
                $lineCents = intdiv($priceCents * $quantityHundredths + 50, 100);
                if ($lineCents > 999999999999999999 - $total) {
                    throw ValidationException::withMessages(['items' => 'The quoted total exceeds the supported amount. Please check the unit prices.']);
                }
                $total += $lineCents;
                $items[] = ['rfq_item_id' => $item['id'], 'unit_price' => $this->moneyFromCents($priceCents),
                    'total' => $this->moneyFromCents($lineCents)];
            }
            $submittedAt = now();
            $response = collect($data)->only(['supplier_contact', 'supplier_tin', 'supplier_address', 'signatory_name',
                'signatory_position', 'delivery_days', 'philgeps_registration_number', 'philgeps_registration_type', 'supplier_remarks'])->all();
            $response += ['supplier_name' => $locked->supplier_name, 'supplier_email' => $locked->supplier_email,
                'items' => $items, 'total_amount' => $this->moneyFromCents($total),
                'issued_snapshot_hash' => RfqSupplierQuotation::hashSnapshot($locked->issued_snapshot),
                'signature_hash' => hash('sha256', $signature), 'consent_text' => RfqSupplierWorkflowService::CONSENT,
                'submitted_at' => $submittedAt->toIso8601String()];
            $locked->fill(collect($data)->only(['supplier_contact', 'supplier_tin', 'supplier_address', 'signatory_name', 'signatory_position',
                'delivery_days', 'philgeps_registration_number', 'philgeps_registration_type', 'supplier_remarks'])->all());
            $locked->fill(['status' => 'submitted', 'signature_data' => $signature, 'response_snapshot' => $response,
                'response_hash' => RfqSupplierQuotation::hashSnapshot($response),
                'total_amount' => $response['total_amount'], 'consent_text' => RfqSupplierWorkflowService::CONSENT,
                'submitted_at' => $submittedAt, 'signer_ip_address' => $request->ip(),
                'signer_user_agent' => mb_substr($request->userAgent() ?? '', 0, 512)])->save();
            app(SvpChainService::class)->linkRfq($rfq, null, 'Signed supplier quotation received', 'Supplier: '.$locked->supplier_name.'; awaiting staff verification.');
            $workflow->notifyOwner($rfq, 'Signed supplier quotation received', $locked->supplier_name.' submitted a signed quotation for RFQ '.$rfq->rfq_number.'. Review it in Supplier Quotations.');
        });
        return redirect()->route('rfq-response.show', $token)->with('status', 'Your signed quotation has been submitted. The requesting office will review it.');
    }
}
