<?php

namespace App\Services;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSupplier;
use App\Models\CompetitiveBiddingSupportingItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitiveBiddingSupportingDocumentService
{
    private const PROJECT_REQUIREMENTS = [
        ['1', 'annual_investment_program', 'Procurement Planning', 'planning', 'Annual Investment Program (AIP)', 'BACSEC-003'],
        ['2', 'annual_procurement_plan', 'Procurement Planning', 'planning', 'Annual Procurement Plan (APP)', 'BACSEC-003'],
        ['3', 'approved_budget_contract', 'Procurement Planning', 'planning', 'Approved Budget for the Contract (ABC)', 'BACSEC-003'],
        ['4', 'philgeps_bid_notice_abstract', 'Procurement Planning', 'planning', 'PhilGEPS Bid Notice Abstract', 'BACSEC-003'],
        ['5', 'bac_secretariat_head_certification', 'Procurement Planning', 'planning', 'Certification of the Head of the BAC Secretariat', 'BACSEC-003'],
        ['6.1', 'invitation_to_bid', 'Bidding Documents', 'bidding', 'Invitation to Bid', 'BACSEC-003'],
        ['6.2', 'instructions_to_bidders', 'Bidding Documents', 'bidding', 'Instructions to Bidders', 'BACSEC-003'],
        ['6.3', 'bid_data_sheet', 'Bidding Documents', 'bidding', 'Bid Data Sheet', 'BACSEC-003'],
        ['6.4', 'general_conditions_contract', 'Bidding Documents', 'bidding', 'General Conditions of Contract', 'BACSEC-003'],
        ['6.5', 'special_conditions_contract', 'Bidding Documents', 'bidding', 'Special Conditions of Contract', 'BACSEC-003'],
        ['27', 'invitation_to_observer', 'BAC Proceedings', 'bidding', 'Letter of Invitation to Observer', 'BACSEC-003'],
        ['28', 'technical_financial_checklist_copy', 'BAC Proceedings', 'bidding', 'Completed Checklist of Technical and Financial Requirements', 'BACSEC-001'],
        ['29', 'abstract_bids_read_calculated', 'BAC Proceedings', 'evaluation', 'Abstract of Bids as Read and as Calculated, signed by the BAC', 'BACSEC-001'],
        ['30', 'bid_opening_minutes_attendance', 'BAC Proceedings', 'evaluation', 'Minutes of Bid Opening with Attendance', 'BACSEC-001'],
        ['31', 'bid_evaluation_report', 'Evaluation and Post Qualification', 'evaluation', 'Bid Evaluation Report', 'BACSEC-001'],
        ['32', 'post_qualification_summary', 'Evaluation and Post Qualification', 'post_qualification', 'Post Qualification Evaluation Summary Report', 'BACSEC-001'],
        ['33', 'twg_post_qualification_report', 'Evaluation and Post Qualification', 'post_qualification', 'Post Qualification Report of the Technical Working Group', 'Technical Working Group'],
        ['34', 'notice_post_qualification', 'Evaluation and Post Qualification', 'post_qualification', 'Notice of Post Qualification', 'BACSEC-001'],
        ['35', 'bac_resolution_post_qualification', 'Evaluation and Post Qualification', 'post_qualification', 'BAC Resolution on Post Qualification, Declaration of SCRB, and Recommendation to HOPE', 'BAC'],
        ['36', 'notice_to_scrb_bidder', 'Evaluation and Post Qualification', 'post_qualification', 'Notice issued by the BAC to the Bidder declared as SCRB', 'BAC'],
        ['37', 'notice_of_award', 'Award and Contract', 'award', 'Notice of Award of Contract', 'HOPE / BACSEC-001'],
        ['39', 'contract_agreement', 'Award and Contract', 'post_award', 'Contract Agreement approved by the HOPE / Approved Contract', 'HOPE / BACSEC-001'],
        ['40', 'purchase_order', 'Award and Contract', 'post_award', 'Purchase Order', 'BACSEC-003'],
        ['41', 'notice_to_proceed', 'Award and Contract', 'post_award', 'Notice to Proceed received and dated by the contractor', 'HOPE / Contractor'],
    ];

    private const SUPPLIER_REQUIREMENTS = [
        ['7', 'letter_of_intent', 'Bidder Submission', 'bidding', 'Letter of Intent', 'Supplier'],
        ['8', 'financial_bid_form', 'Bidder Submission', 'bidding', 'Financial Bid Form', 'Supplier'],
        ['9', 'price_schedule_local_goods', 'Bidder Submission', 'bidding', 'Price Schedule for Goods Offered from Within the Philippines', 'Supplier'],
        ['10', 'philgeps_platinum_registration', 'Eligibility Documents', 'bidding', 'PhilGEPS Platinum Registration', 'Supplier'],
        ['11', 'business_registration', 'Eligibility Documents', 'bidding', 'DTI / SEC / CDA Business Registration', 'Supplier'],
        ['12', 'business_permit', 'Eligibility Documents', 'bidding', 'Valid Mayor\'s or Business Permit', 'Supplier'],
        ['13', 'tax_clearance', 'Eligibility Documents', 'bidding', 'Tax Clearance', 'Supplier'],
        ['14', 'bir_registration', 'Eligibility Documents', 'bidding', 'BIR Certificate of Registration', 'Supplier'],
        ['15', 'omnibus_sworn_statement', 'Eligibility Documents', 'bidding', 'Omnibus Sworn Statement', 'Supplier'],
        ['16', 'nfcc', 'Financial Capacity', 'bidding', 'Net Financial Contracting Capacity (NFCC)', 'Supplier'],
        ['17', 'audited_financial_statement', 'Financial Capacity', 'bidding', 'Audited Financial Statements', 'Supplier'],
        ['18', 'annual_income_tax_return', 'Financial Capacity', 'bidding', 'Annual Income Tax Return', 'Supplier'],
        ['19', 'quarterly_income_tax_return', 'Financial Capacity', 'bidding', 'Quarterly Income Tax Return', 'Supplier'],
        ['21', 'ongoing_contracts_statement', 'Experience and Capacity', 'bidding', 'Statement of All Ongoing, Completed, and Awarded but Not Yet Started Related Contracts', 'Supplier'],
        ['22', 'slcc_statement', 'Experience and Capacity', 'bidding', 'Statement of Single Largest Completed Contract (SLCC)', 'Supplier'],
        ['23', 'bid_securing_declaration', 'Technical Submission', 'bidding', 'Bid Securing Declaration or Bid Security', 'Supplier'],
        ['24', 'technical_specifications', 'Technical Submission', 'bidding', 'Technical Specifications', 'Supplier'],
        ['25', 'schedule_of_requirements', 'Technical Submission', 'bidding', 'Schedule of Requirements', 'Supplier'],
        ['26', 'organizational_chart', 'Technical Submission', 'bidding', 'Organizational Chart', 'Supplier'],
        ['38', 'performance_bond', 'Post-Award Requirement', 'post_award', 'Performance Bond', 'Awarded Supplier'],
    ];

    public function createRecord(array $attributes): CompetitiveBiddingRecord
    {
        return DB::transaction(function () use ($attributes) {
            $record = CompetitiveBiddingRecord::create($attributes);
            $this->seedItems($record, null, self::PROJECT_REQUIREMENTS);

            return $record;
        });
    }

    public function addSupplier(CompetitiveBiddingRecord $record, array $attributes): CompetitiveBiddingSupplier
    {
        return DB::transaction(function () use ($record, $attributes) {
            $supplier = $record->suppliers()->create($attributes);
            $this->seedItems($record, $supplier, self::SUPPLIER_REQUIREMENTS);

            return $supplier;
        });
    }

    public function recordSummary(CompetitiveBiddingRecord $record): array
    {
        $projectItems = $record->supportingItems->whereNull('competitive_bidding_supplier_id');

        return [
            'project' => $this->summarize($projectItems),
            'suppliers' => $record->suppliers->mapWithKeys(fn (CompetitiveBiddingSupplier $supplier) => [
                $supplier->id => $this->supplierSummary($supplier),
            ])->all(),
        ];
    }

    public function supplierSummary(CompetitiveBiddingSupplier $supplier): array
    {
        $requiredItems = $supplier->supportingItems
            ->filter(fn (CompetitiveBiddingSupportingItem $item) => $item->isEffectivelyRequired());
        $summary = $this->summarize($requiredItems);
        $hasProblem = $requiredItems->contains(fn (CompetitiveBiddingSupportingItem $item) => in_array(
            $item->status,
            [CompetitiveBiddingSupportingItem::STATUS_MISSING, CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION],
            true
        ));

        $summary['review_label'] = match (true) {
            $supplier->review_status === CompetitiveBiddingSupplier::STATUS_DISQUALIFIED => 'Disqualified',
            $hasProblem => 'For Disqualification Review',
            $summary['required'] > 0 && $summary['verified'] === $summary['required'] => 'Complete',
            default => 'Incomplete',
        };

        return $summary;
    }

    private function summarize(Collection $items): array
    {
        $required = $items->filter(fn (CompetitiveBiddingSupportingItem $item) => $item->isEffectivelyRequired());
        $verified = $required->where('status', CompetitiveBiddingSupportingItem::STATUS_VERIFIED)->count();

        return [
            'required' => $required->count(),
            'verified' => $verified,
            'attached' => $required->where('status', CompetitiveBiddingSupportingItem::STATUS_ATTACHED)->count(),
            'issues' => $required->whereIn('status', [
                CompetitiveBiddingSupportingItem::STATUS_MISSING,
                CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
            ])->count(),
            'percent' => $required->count() > 0 ? (int) round(($verified / $required->count()) * 100) : 0,
        ];
    }

    private function seedItems(
        CompetitiveBiddingRecord $record,
        ?CompetitiveBiddingSupplier $supplier,
        array $requirements
    ): void {
        foreach ($requirements as $index => [$number, $key, $section, $phase, $name, $source]) {
            $record->supportingItems()->create([
                'competitive_bidding_supplier_id' => $supplier?->id,
                'guide_number' => $number,
                'requirement_key' => $key,
                'display_order' => $index + 1,
                'section' => $section,
                'phase' => $phase,
                'requirement_name' => $name,
                'document_source' => $source,
                'is_required' => true,
                'status' => CompetitiveBiddingSupportingItem::STATUS_PENDING,
            ]);
        }
    }
}
