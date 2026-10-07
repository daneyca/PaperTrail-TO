<?php

namespace Database\Seeders;

use App\Models\AiAssistRule;
use App\Models\DelayThresholdRule;
use App\Models\DocumentRequirementRule;
use App\Models\NotificationTemplate;
use App\Models\RoutingRule;
use Illuminate\Database\Seeder;

class PaperTrailRuleConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $this->documentRequirements();
        $this->aiRules();
        $this->routingRules();
        $this->delayThresholds();
        $this->notificationTemplates();
    }

    private function documentRequirements(): void
    {
        $rules = [
            ['purchase_request', 'Purpose field required', 'field', 'purpose', null, 'Purpose must describe the procurement need.', true, 'critical', 'draft', 10],
            ['purchase_request', 'At least one item row required', 'field', 'items', null, 'Purchase Request must include at least one complete item row.', true, 'critical', 'draft', 20],
            ['purchase_request', 'APP reference / Supplemental APP attachment suggested', 'attachment', null, 'supporting_document', 'Attach APP reference, Supplemental APP, or equivalent procurement planning support if applicable.', false, 'warning', null, 30],
            ['purchase_request', 'Supporting quotation if applicable', 'attachment', null, 'quotation', 'Attach quotations or canvass documents when already available.', false, 'warning', null, 40],
            ['bac_resolution', 'Linked PR required', 'workflow', 'source_pr_document_id', null, 'BAC Resolution should be linked to its source Purchase Request.', true, 'critical', null, 10],
            ['bac_resolution', 'RFQ / Quotation attachment suggested', 'attachment', null, 'quotation', 'Attach RFQ or quotation support for the BAC Resolution.', false, 'warning', null, 20],
            ['bac_resolution', 'Abstract attachment suggested', 'attachment', null, 'bac_document', 'Attach Abstract of Quotations support when available.', false, 'warning', null, 30],
            ['bac_resolution', 'BAC Chair signature required', 'signatory', 'bac_chair_signature', null, 'BAC Chair signature is required before final release.', true, 'critical', 'for_signature', 40],
            ['app', 'Fiscal year required', 'field', 'fiscal_year', null, 'Annual Procurement Plan requires a fiscal year.', true, 'critical', 'draft', 10],
            ['app', 'At least one APP item required', 'field', 'items', null, 'Annual Procurement Plan requires at least one item.', true, 'critical', 'draft', 20],
            ['app', 'Total estimated budget required', 'amount', 'total_amount', null, 'APP total estimated budget must be greater than zero.', true, 'critical', 'draft', 30],
            ['purchase_order', 'Supplier name required', 'field', 'supplier_name', null, 'Purchase Order must identify the supplier.', true, 'critical', 'draft', 10],
            ['purchase_order', 'PR number required', 'field', 'pr_no', null, 'Purchase Order should reference an official PR number.', true, 'critical', 'draft', 20],
            ['purchase_order', 'Total amount required', 'amount', 'total_amount', null, 'Purchase Order total amount must be greater than zero.', true, 'critical', 'draft', 30],
            ['inspection_acceptance', 'Source PO required', 'workflow', 'purchase_order_id', null, 'Inspection / Acceptance must be linked to a Purchase Order.', true, 'critical', 'draft', 10],
            ['inspection_acceptance', 'Delivered items required', 'field', 'delivered_items', null, 'Inspection / Acceptance requires delivered item details.', true, 'critical', 'draft', 20],
            ['inspection_acceptance', 'Acceptance remarks required', 'field', 'acceptance_remarks', null, 'Acceptance remarks should be recorded before completion.', true, 'warning', 'draft', 30],
        ];

        foreach ($rules as [$documentType, $ruleName, $type, $fieldKey, $category, $description, $required, $severity, $status, $sortOrder]) {
            DocumentRequirementRule::updateOrCreate(
                [
                    'document_type' => $documentType,
                    'rule_name' => $ruleName,
                ],
                [
                    'requirement_type' => $type,
                    'field_key' => $fieldKey,
                    'attachment_category' => $category,
                    'description' => $description,
                    'is_required' => $required,
                    'severity' => $severity,
                    'applies_to_status' => $status,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    private function aiRules(): void
    {
        $rules = [
            [
                'completeness_check',
                'purchase_request',
                'PR Completeness Check',
                'Check whether the Purchase Request has purpose, at least one item, estimated cost, office name, and required supporting attachments. Return missing fields, warnings, and recommended next step.',
                [
                    'completeness_score' => 'number',
                    'missing_fields' => [],
                    'missing_attachments' => [],
                    'warnings' => [],
                    'recommended_next_step' => 'string',
                ],
                'Configuration only. Do not call an AI provider until OpenAI credentials are enabled.',
                10,
            ],
            [
                'routing_suggestion',
                'purchase_request',
                'PR Routing Suggestion',
                'Review the current status, office, amount, and attachments, then recommend the next routing office and status.',
                [
                    'recommended_route' => 'string',
                    'confidence' => 'number',
                    'reason' => 'string',
                ],
                'Use configured routing rules as the source of truth.',
                20,
            ],
            [
                'delay_risk',
                null,
                'General Delay Risk Assessment',
                'Assess whether a procurement document is at risk of delay based on configured stage thresholds and routing age.',
                [
                    'risk_level' => 'info|warning|critical',
                    'days_in_stage' => 'number',
                    'recommended_action' => 'string',
                ],
                'Future AI-ready rule. Current delay logic should remain rule-based.',
                30,
            ],
        ];

        foreach ($rules as [$featureType, $documentType, $ruleName, $prompt, $schema, $note, $sortOrder]) {
            AiAssistRule::updateOrCreate(
                [
                    'feature_type' => $featureType,
                    'document_type' => $documentType,
                    'rule_name' => $ruleName,
                ],
                [
                    'prompt_instruction' => $prompt,
                    'expected_output_schema' => $schema,
                    'system_note' => $note,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    private function routingRules(): void
    {
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
            RoutingRule::updateOrCreate(
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
                ]
            );
        }
    }

    private function delayThresholds(): void
    {
        $rules = [
            ['purchase_request', 'PR Number Assignment', 'pending_pr_number_assignment', 1, 2, 3, 5, 'PR Numbering Staff'],
            ['bac_resolution', 'BAC Chair Signature', 'pending_bac_chair_signature', 1, 2, 4, 7, 'BAC Chair'],
            ['purchase_request', 'BAC Secretariat PR Validation', 'under_pr_validation', 1, 3, 5, 7, 'BAC Secretariat'],
        ];

        foreach ($rules as [$documentType, $stage, $status, $low, $medium, $high, $critical, $notifyRole]) {
            DelayThresholdRule::updateOrCreate(
                [
                    'document_type' => $documentType,
                    'stage' => $stage,
                    'status' => $status,
                ],
                [
                    'low_risk_days' => $low,
                    'medium_risk_days' => $medium,
                    'high_risk_days' => $high,
                    'critical_risk_days' => $critical,
                    'notify_role' => $notifyRole,
                    'is_active' => true,
                ]
            );
        }
    }

    private function notificationTemplates(): void
    {
        $templates = [
            ['pr_submitted_for_numbering', 'both', 'New Purchase Request for PR Number Assignment', 'A Purchase Request from {office_name} has been submitted and is waiting for official PR number assignment.', 'Review Purchase Request'],
            ['pr_number_assigned', 'both', 'PR Number Assigned', 'Your {document_type} with tracking number {tracking_number} has been assigned an official PR number.', 'View Purchase Request'],
            ['bac_resolution_for_signature', 'both', 'BAC Resolution for Confirmation', 'A BAC Resolution with tracking number {tracking_number} has been routed to you for confirmation.', 'Review Resolution'],
            ['signature_request_created', 'both', 'Document Requires Your Signature', 'A {document_type} with tracking number {tracking_number} has been routed to you for electronic signature.', 'Review and Sign'],
            ['document_returned', 'both', 'Document Returned for Correction', 'A {document_type} from {office_name} was returned and requires attention.', 'Open Document'],
            ['delay_alert', 'both', 'Delay Risk Alert', 'A {document_type} with tracking number {tracking_number} may be delayed at {status}.', 'Review Document'],
        ];

        foreach ($templates as [$key, $channel, $subject, $body, $actionText]) {
            NotificationTemplate::updateOrCreate(
                ['template_key' => $key],
                [
                    'channel' => $channel,
                    'subject' => $subject,
                    'body' => $body,
                    'action_text' => $actionText,
                    'is_active' => true,
                ]
            );
        }
    }
}
