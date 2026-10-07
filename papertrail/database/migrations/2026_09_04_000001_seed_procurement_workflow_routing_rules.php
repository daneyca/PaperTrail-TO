<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('routing_rules')) {
            return;
        }

        $now = now();
        $rules = [
            ['ppmp', 'ppmp_draft', 'pending_ppmp_review', 'Head Office / End User', 'BAC Secretariat', 'Submit PPMP Document', 'PPMP procurement documents should be submitted by the requesting office to BAC Secretariat for review.', false, true, 5],
            ['ppmp', 'pending_ppmp_review', 'under_ppmp_review', 'Head Office / End User', 'BAC Secretariat', 'Start PPMP Document Review', 'Submitted PPMP procurement documents should move into BAC Secretariat review.', false, false, 6],
            ['ppmp', 'under_ppmp_review', 'accepted_for_app_consolidation', 'BAC Secretariat', 'BAC Secretariat', 'Accept PPMP Document', 'Reviewed PPMP procurement documents may be accepted for APP consolidation.', false, false, 7],
            ['ppmp', 'accepted_for_app_consolidation', null, 'BAC Secretariat', 'BAC Secretariat', 'Include in APP Consolidation', 'Accepted PPMP procurement documents should be included in APP consolidation.', false, false, 8],
            ['ppmp_record', 'draft', 'submitted', 'Head Office / End User', 'BAC Secretariat', 'Submit PPMP', 'Draft PPMP records should be submitted by the requesting office to BAC Secretariat for review.', false, true, 10],
            ['ppmp_record', 'submitted', 'reviewed', 'Head Office / End User', 'BAC Secretariat', 'Review PPMP', 'Submitted PPMP records should be reviewed by BAC Secretariat before APP consolidation.', false, false, 20],
            ['ppmp_record', 'reviewed', 'approved', 'BAC Secretariat', 'BAC Secretariat', 'Accept for APP Consolidation', 'Reviewed PPMP records may be accepted for APP consolidation.', false, false, 30],
            ['ppmp_record', 'returned', 'submitted', 'Head Office / End User', 'BAC Secretariat', 'Resubmit PPMP', 'Returned PPMP records should be corrected by the requesting office before resubmission.', false, true, 40],
            ['app', 'draft', 'submitted', 'BAC Secretariat', 'Head of the Procuring Entity', 'Submit APP for Approval', 'Draft APP records prepared by BAC Secretariat should be submitted to HOPE for approval.', false, false, 50],
            ['app', 'consolidated', 'submitted', 'BAC Secretariat', 'Head of the Procuring Entity', 'Route Consolidated APP', 'Consolidated APP records should proceed to HOPE approval.', false, false, 60],
            ['app', 'submitted', 'approved', 'Head of the Procuring Entity', 'BAC Secretariat', 'Approve APP', 'APP records for approval should be reviewed by HOPE before use as procurement reference.', false, false, 70],
            ['app', 'returned', 'submitted', 'BAC Secretariat', 'Head of the Procuring Entity', 'Resubmit APP', 'Returned APP records should be corrected by BAC Secretariat before resubmission.', false, false, 80],
            ['purchase_request', 'pr_draft', 'pending_pr_number_assignment', 'Head Office / End User', 'PR Numbering Staff', 'Submit for PR Number', 'Draft Purchase Requests route to PR Numbering Staff for official number assignment.', false, true, 90],
            ['purchase_request', 'pending_pr_number_assignment', 'pr_number_assigned_returned_to_end_user', 'PR Numbering Staff', 'Head Office / End User', 'Assign PR Number', 'PR Numbering Staff assigns the official PR number and returns the request to the requesting office.', false, false, 100],
            ['purchase_request', 'pr_number_assigned_returned_to_end_user', 'submitted_to_bac_secretariat', 'Head Office / End User', 'BAC Secretariat', 'Submit to BAC Secretariat', 'PR-numbered Purchase Requests route to BAC Secretariat for validation.', false, true, 110],
            ['purchase_request', 'submitted_to_bac_secretariat', 'pr_received_by_bac_secretariat', 'Head Office / End User', 'BAC Secretariat', 'Acknowledge PR Receipt', 'BAC Secretariat should acknowledge receipt before validation starts.', false, false, 120],
            ['purchase_request', 'pr_received_by_bac_secretariat', 'under_pr_validation', 'BAC Secretariat', 'BAC Secretariat', 'Start PR Validation', 'Received Purchase Requests should undergo BAC Secretariat validation.', false, false, 130],
            ['purchase_request', 'under_pr_validation', 'ready_for_bac_resolution', 'BAC Secretariat', 'BAC Secretariat', 'Ready for BAC Processing', 'Validated Purchase Requests may proceed to BAC processing and resolution preparation.', false, false, 140],
            ['rfq', 'draft', 'submitted', 'BAC Secretariat', 'BAC Secretariat', 'Submit RFQ', 'RFQ drafts should be completed and submitted before supplier quotation recording.', false, false, 150],
            ['rfq', 'submitted', 'issued', 'BAC Secretariat', 'Supplier', 'Issue RFQ', 'Submitted RFQs should be issued to suppliers for quotation.', false, false, 160],
            ['rfq', 'issued', 'quoted', 'Supplier', 'BAC Secretariat', 'Record Supplier Quotations', 'Issued RFQs should receive supplier quotations before abstract preparation.', false, false, 170],
            ['rfq', 'quoted', 'abstract_draft', 'BAC Secretariat', 'BAC Secretariat', 'Prepare Abstract', 'Supplier quotations should be summarized in an Abstract of Quotations.', false, false, 180],
            ['abstract', 'draft', 'submitted', 'BAC Secretariat', 'BAC Secretariat', 'Submit Abstract', 'Abstract drafts should be submitted after supplier quotation encoding.', false, false, 190],
            ['abstract', 'submitted', 'ready_for_po', 'BAC Secretariat', 'BAC Secretariat', 'Recommend Award / Prepare PO', 'Submitted Abstracts support BAC recommendation and Purchase Order preparation.', false, false, 200],
            ['bac_resolution', 'draft', 'submitted_to_bac_chair', 'BAC Secretariat', 'BAC Chair', 'Submit to BAC Chair', 'BAC Resolution drafts route to BAC Chairperson for confirmation/signature.', true, false, 210],
            ['bac_resolution', 'submitted_to_bac_chair', 'confirmed_by_bac_chair', 'BAC Chair', 'BAC Secretariat', 'Confirm BAC Resolution', 'BAC Chairperson reviews and signs or returns the BAC Resolution.', true, false, 220],
            ['bac_resolution', 'confirmed_by_bac_chair', 'forwarded_to_hope', 'BAC Secretariat', 'Head of the Procuring Entity', 'Forward to HOPE', 'Confirmed BAC Resolutions route to HOPE when final approval is required.', true, false, 230],
            ['bac_resolution', 'forwarded_to_hope', 'approved_by_hope', 'Head of the Procuring Entity', 'BAC Secretariat', 'Approve BAC Resolution', 'HOPE reviews and approves or returns the BAC Resolution.', false, false, 240],
            ['purchase_order', 'draft', 'budget_review', 'BAC Secretariat', 'Budget Office', 'Route PO to Budget', 'Purchase Orders prepared from approved BAC Resolution should go to Budget Office for verification.', false, true, 250],
            ['purchase_order', 'budget_review', 'accounting_review', 'Budget Office', 'Accounting Office', 'Route PO to Accounting', 'Budget-verified Purchase Orders should proceed to Accounting Office for processing.', false, false, 260],
            ['purchase_order', 'accounting_review', 'approved', 'Accounting Office', 'Head of the Procuring Entity', 'Route PO for Approval', 'Accounting-processed Purchase Orders should be routed to HOPE for approval.', false, false, 270],
            ['purchase_order', 'approved', 'issued', 'Head of the Procuring Entity', 'Supplier', 'Issue Purchase Order', 'Approved Purchase Orders may be issued to the supplier.', false, false, 280],
            ['inspection_acceptance', 'draft', 'inspected', 'Supplier', 'Inspection Committee', 'Inspect Delivery', 'Supplier delivery should be inspected by the Inspection Committee.', false, false, 290],
            ['inspection_acceptance', 'inspected', 'accepted', 'Inspection Committee', 'Head Office / End User', 'Confirm Acceptance', 'Inspected delivery should be confirmed by the end user or requesting office.', false, false, 300],
            ['inspection_acceptance', 'accepted', 'completed', 'Head Office / End User', 'Accounting Office', 'Complete Inspection and Acceptance', 'Accepted delivery records may proceed to Accounting processing and completion.', false, false, 310],
        ];

        foreach ($rules as [$documentType, $current, $next, $fromRole, $toRole, $label, $description, $signature, $attachmentCheck, $sortOrder]) {
            DB::table('routing_rules')->updateOrInsert(
                [
                    'document_type' => $documentType,
                    'current_status' => $current,
                    'next_status' => $next,
                    'route_label' => $label,
                ],
                [
                    'from_role' => $fromRole,
                    'to_role' => $toRole,
                    'rule_description' => $description,
                    'requires_signature' => $signature,
                    'requires_attachment_check' => $attachmentCheck,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('routing_rules')) {
            return;
        }

        DB::table('routing_rules')
            ->whereIn('route_label', [
                'Submit PPMP',
                'Review PPMP',
                'Accept for APP Consolidation',
                'Resubmit PPMP',
                'Submit APP for Approval',
                'Route Consolidated APP',
                'Approve APP',
                'Resubmit APP',
                'Submit for PR Number',
                'Assign PR Number',
                'Submit to BAC Secretariat',
                'Acknowledge PR Receipt',
                'Start PR Validation',
                'Ready for BAC Processing',
                'Submit RFQ',
                'Issue RFQ',
                'Record Supplier Quotations',
                'Prepare Abstract',
                'Submit Abstract',
                'Recommend Award / Prepare PO',
                'Submit to BAC Chair',
                'Confirm BAC Resolution',
                'Forward to HOPE',
                'Approve BAC Resolution',
                'Route PO to Budget',
                'Route PO to Accounting',
                'Route PO for Approval',
                'Issue Purchase Order',
                'Inspect Delivery',
                'Confirm Acceptance',
                'Complete Inspection and Acceptance',
                'Submit PPMP Document',
                'Start PPMP Document Review',
                'Accept PPMP Document',
                'Include in APP Consolidation',
            ])
            ->delete();
    }
};
