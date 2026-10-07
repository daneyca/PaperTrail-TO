<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Ppmp;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RoutingRule;
use App\Models\SupplementalApp;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiRouteValidationService
{
    private const SUPPORTED_TYPES = [
        'ppmp',
        'ppmp_record',
        'app',
        'supplemental_app',
        'purchase_request',
        'procurement_document',
        'bac_resolution',
        'rfq',
        'abstract',
        'purchase_order',
        'inspection_acceptance',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly DocumentResolverService $documents,
        private readonly RuleConfigurationService $rules,
    ) {
    }

    public function validateRoute(User $user, string $documentType, int $documentId): array
    {
        $startedAt = microtime(true);
        $type = $this->canonicalType($documentType);

        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('AI route validation is not available for this document type yet.');
        }

        $document = $this->documents->resolve($type, $documentId);

        if (! $this->documents->canView($user, $document)) {
            AuditLogger::denied('unauthorized_ai_route_validation_attempt', [
                'module' => 'AI Route Validation',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $this->documents->displayTrackingNumber($document),
                'user_id' => $user->id,
                'severity' => 'warning',
            ]);

            throw new AuthorizationException('You do not have permission to validate this document route.');
        }

        $trackingNumber = $this->documents->displayTrackingNumber($document);

        AuditLogger::ai('ai_route_validation_started', $document, [
            'module' => 'AI Route Validation',
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $trackingNumber,
            'user_id' => $user->id,
            'execution_time' => 0,
        ]);

        Log::info('ai_route_validation_started', [
            'user_id' => $user->id,
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $trackingNumber,
            'execution_time' => 0,
        ]);

        try {
            $document->loadMissing($this->relationshipsFor($document));

            $context = $this->buildContext($document, $type);
            $validation = $this->validateContext($context);
            $aiMessage = $this->aiExplanation($context, $validation)
                ?: $this->fallbackExplanation($context, $validation);
            $executionTime = round(microtime(true) - $startedAt, 3);

            AuditLogger::ai('ai_route_validation_completed', $document, [
                'module' => 'AI Route Validation',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'user_id' => $user->id,
                'route_status' => $validation['status'],
                'warning_count' => count($validation['warnings']),
                'rule_source' => $context['rule_source'],
                'execution_time' => $executionTime,
            ]);

            Log::info('ai_route_validation_completed', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'route_status' => $validation['status'],
                'rule_source' => $context['rule_source'],
                'execution_time' => $executionTime,
            ]);

            return [
                ...$context,
                ...$validation,
                'ai_message' => $aiMessage,
                'execution_time' => $executionTime,
            ];
        } catch (Throwable $exception) {
            $executionTime = round(microtime(true) - $startedAt, 3);

            Log::error('AI route validation failed.', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'execution_time' => $executionTime,
            ]);

            AuditLogger::ai('ai_route_validation_failed', $document, [
                'module' => 'AI Route Validation',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'user_id' => $user->id,
                'severity' => 'warning',
                'failure' => $exception::class,
                'execution_time' => $executionTime,
            ]);

            throw new RuntimeException('AI route validation is temporarily unavailable.');
        }
    }

    private function buildContext(Model $document, string $type): array
    {
        $source = $this->sourceProcurementDocument($document);

        if ($source) {
            $source->loadMissing([
                'submittingOffice',
                'currentOffice',
                'assignedTo',
                'routeDestinationOffice',
                'submittedBy',
                'preparedBy',
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ]);
        }

        $status = $this->statusFor($document);
        $configuredRule = $this->configuredRuleFor($type, $status);
        $builtInRule = $this->builtInRuleFor($document, $type, $status);
        $rule = $configuredRule ?: $builtInRule;
        $currentStage = $this->stageFor($document) ?: Arr::get($rule, 'expected_current_stage');
        $currentHolder = $this->currentHolderFor($document, $source) ?: Arr::get($rule, 'expected_current_holder');
        $expectedNextStatus = Arr::get($rule, 'expected_next_status');

        return [
            'document_type' => $this->typeLabel($type, $document),
            'document_type_key' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $this->documents->displayTrackingNumber($document) ?: '#' . $document->getKey(),
            'title' => $this->titleFor($document),
            'requesting_office' => $this->documents->officeNameFor($document) ?: 'Not recorded',
            'current_status' => $status ?: 'not_recorded',
            'current_status_label' => $this->humanizeStatus($status),
            'current_stage' => $currentStage ?: $this->humanizeStatus($status),
            'current_holder' => $currentHolder ?: 'Not recorded',
            'expected_next_status' => $expectedNextStatus,
            'expected_next_status_label' => $this->humanizeStatus($expectedNextStatus),
            'expected_next_stage' => Arr::get($rule, 'expected_next_stage'),
            'expected_next_holder' => Arr::get($rule, 'expected_next_holder'),
            'expected_current_holder' => Arr::get($rule, 'expected_current_holder'),
            'next_action' => Arr::get($rule, 'next_action'),
            'rule_description' => Arr::get($rule, 'rule_description'),
            'rule_source' => Arr::get($rule, 'source', 'built_in_workflow'),
            'requires_signature' => (bool) Arr::get($rule, 'requires_signature', false),
            'requires_attachment_check' => (bool) Arr::get($rule, 'requires_attachment_check', false),
            'terminal' => (bool) Arr::get($rule, 'terminal', false),
            'history' => $this->historyFor($document, $source),
        ];
    }

    private function validateContext(array $context): array
    {
        $checks = [];
        $warnings = [];

        if (($context['current_status'] ?? 'not_recorded') === 'not_recorded') {
            $checks[] = $this->check('Current status recorded', 'warning', 'No workflow status is recorded for this document.');
            $warnings[] = 'Current workflow status is missing.';
        } else {
            $checks[] = $this->check('Current status recorded', 'passed', $context['current_status_label']);
        }

        if (($context['current_holder'] ?? 'Not recorded') === 'Not recorded') {
            $checks[] = $this->check('Current holder identified', 'warning', 'No current office or assigned user is recorded.');
            $warnings[] = 'Current holder is not clearly recorded.';
        } else {
            $checks[] = $this->check('Current holder identified', 'passed', $context['current_holder']);
        }

        if ($context['terminal']) {
            $checks[] = $this->check('Next route required', 'passed', 'No next route is required for this completed or closed status.');
        } elseif (filled($context['expected_next_holder']) || filled($context['expected_next_status'])) {
            $checks[] = $this->check(
                'Next route identified',
                'passed',
                trim(($context['expected_next_holder'] ?: 'Next handler') . ' - ' . ($context['expected_next_status_label'] ?: 'Next status'), ' -')
            );
        } else {
            $checks[] = $this->check('Next route identified', 'warning', 'No configured or built-in next route was found.');
            $warnings[] = 'No matching route rule was found for this status.';
        }

        if ($context['requires_attachment_check']) {
            $checks[] = $this->check('Attachment gate', 'info', 'Configured route requires attachment/completeness checking before movement.');
        }

        if ($context['requires_signature']) {
            $checks[] = $this->check('Signature gate', 'info', 'Configured route requires a signature action before movement.');
        }

        if (str_contains((string) $context['current_status'], 'returned')) {
            $warnings[] = 'Document is currently returned; correction or resubmission is expected before normal routing continues.';
        }

        if (($context['rule_source'] ?? '') === 'fallback') {
            $warnings[] = 'Route guidance used a generic fallback because this document type has limited workflow data.';
        }

        $status = 'valid';
        $statusLabel = 'Route Looks Valid';

        if ($context['terminal']) {
            $status = 'complete';
            $statusLabel = 'Workflow Complete';
        } elseif ($warnings !== []) {
            $status = 'warning';
            $statusLabel = 'Review Route Before Moving';
        }

        $uniqueWarnings = array_values(array_unique($warnings));

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'confidence_score' => $this->confidenceScoreFor($status, $uniqueWarnings, $context),
            'checks' => $checks,
            'warnings' => $uniqueWarnings,
            'recommendation' => $this->recommendationFor($context, $uniqueWarnings),
        ];
    }

    private function confidenceScoreFor(string $status, array $warnings, array $context): int
    {
        $score = match ($context['rule_source'] ?? 'built_in_workflow') {
            'configured_rule' => 96,
            'built_in_workflow' => 90,
            default => 72,
        };

        if ($status === 'complete') {
            $score = max($score, 94);
        }

        $score -= min(30, count($warnings) * 12);

        if (! $context['terminal'] && blank($context['expected_next_holder']) && blank($context['expected_next_status'])) {
            $score -= 15;
        }

        return max(50, min(99, $score));
    }

    private function configuredRuleFor(string $type, ?string $status): ?array
    {
        foreach ($this->ruleDocumentTypeCandidates($type) as $candidate) {
            $rules = $this->rules->getRoutingRules($candidate, $status);
            $rule = $status ? $rules->firstWhere('current_status', $status) : null;
            $rule ??= $rules->first();

            if ($rule instanceof RoutingRule) {
                return [
                    'source' => 'configured_rule',
                    'rule_description' => $rule->rule_description,
                    'expected_current_holder' => $rule->fromOffice?->name ?: $this->roleLabel($rule->from_role),
                    'expected_next_holder' => $rule->toOffice?->name ?: $this->roleLabel($rule->to_role),
                    'expected_next_status' => $rule->next_status,
                    'expected_next_stage' => $this->humanizeStatus($rule->next_status),
                    'next_action' => $rule->route_label ?: 'Route according to the active workflow rule.',
                    'requires_signature' => (bool) $rule->requires_signature,
                    'requires_attachment_check' => (bool) $rule->requires_attachment_check,
                    'terminal' => false,
                ];
            }
        }

        return null;
    }

    private function builtInRuleFor(Model $document, string $type, ?string $status): array
    {
        if ($document instanceof ProcurementDocument) {
            return $this->procurementDocumentRule($document, $status);
        }

        return $this->standaloneRule($type, $status);
    }

    private function procurementDocumentRule(ProcurementDocument $document, ?string $status): array
    {
        $requestingOffice = $this->documents->officeNameFor($document) ?: 'Requesting Office';

        $map = [
            ProcurementDocument::STATUS_PPMP_DRAFT => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PPMP_PREPARATION,
                ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
                ProcurementDocument::STAGE_PPMP_SIGNATURE_WORKFLOW,
                $requestingOffice,
                'Generate the required Head of Office e-signature before PPMP submission.'
            ),
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PPMP_SIGNATURE_WORKFLOW,
                ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
                ProcurementDocument::STAGE_PPMP_SIGNATORIES_COMPLETED,
                $requestingOffice,
                'Complete the required Head of Office e-signature.'
            ),
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PPMP_SIGNATORIES_COMPLETED,
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                ProcurementDocument::STAGE_PPMP_SUBMITTED_TO_BAC,
                'BACSEC-004',
                'Submit the signed PPMP to BACSEC-004 for APP consolidation.'
            ),
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => $this->route(
                'BACSEC-004',
                ProcurementDocument::STAGE_PPMP_SUBMITTED_TO_BAC,
                ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
                ProcurementDocument::STAGE_PPMP_UNDER_APP_CONSOLIDATION,
                'BACSEC-004',
                'Start APP consolidation review.'
            ),
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => $this->route(
                'BACSEC-004',
                ProcurementDocument::STAGE_PPMP_UNDER_APP_CONSOLIDATION,
                ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
                ProcurementDocument::STAGE_ACCEPTED_FOR_APP_CONSOLIDATION,
                'BAC Secretariat APP Consolidation',
                'Accept for APP consolidation or return to the submitting office with remarks.'
            ),
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => $this->route(
                'BAC Secretariat APP Consolidation',
                ProcurementDocument::STAGE_ACCEPTED_FOR_APP_CONSOLIDATION,
                null,
                null,
                null,
                'Include this accepted PPMP in APP consolidation.',
                true
            ),
            ProcurementDocument::STATUS_PR_DRAFT => $this->route(
                $requestingOffice,
                'Purchase Request Draft',
                ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                ProcurementDocument::STAGE_PR_NUMBER_ASSIGNMENT,
                'PR Numbering Staff',
                'Submit the PR for official PR number assignment.'
            ),
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT => $this->route(
                'PR Numbering Staff',
                ProcurementDocument::STAGE_PR_NUMBER_ASSIGNMENT,
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
                ProcurementDocument::STAGE_PR_NUMBER_ASSIGNED,
                $requestingOffice,
                'Assign the official PR number and return the request to the end user.'
            ),
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PR_NUMBER_ASSIGNED,
                ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'BAC Secretariat',
                'Submit the numbered PR to BAC Secretariat.'
            ),
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PR_NUMBER_ASSIGNED,
                ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'BAC Secretariat',
                'Submit the numbered PR to BAC Secretariat.'
            ),
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'BAC Secretariat',
                'Acknowledge receipt of the submitted PR.'
            ),
            ProcurementDocument::STATUS_PR_SUBMITTED => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'BAC Secretariat',
                'Acknowledge receipt of the submitted PR.'
            ),
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'BAC Secretariat',
                'Start PR validation.'
            ),
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
                ProcurementDocument::STAGE_READY_FOR_BAC_RESOLUTION,
                'BAC Secretariat',
                'Validate APP/PPMP support, then prepare BAC Resolution or return with remarks.'
            ),
            ProcurementDocument::STATUS_NO_PPMP_RECORD_FOUND => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
                ProcurementDocument::STATUS_PENDING_SUPPLEMENTAL_APP,
                ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
                'BAC Secretariat',
                'Create or link Supplemental APP support before continuing.'
            ),
            ProcurementDocument::STATUS_PENDING_SUPPLEMENTAL_APP => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
                ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED,
                ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
                'BAC Secretariat',
                'Complete the Supplemental APP support.'
            ),
            ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
                ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
                ProcurementDocument::STAGE_READY_FOR_BAC_RESOLUTION,
                'BAC Secretariat',
                'Proceed to BAC Resolution preparation.'
            ),
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_READY_FOR_BAC_RESOLUTION,
                ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
                ProcurementDocument::STAGE_BAC_RESOLUTION_PREPARATION,
                'BAC Secretariat',
                'Prepare the BAC Resolution.'
            ),
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_RESOLUTION_PREPARATION,
                ProcurementDocument::STATUS_READY_FOR_RFQ,
                ProcurementDocument::STAGE_READY_FOR_RFQ,
                $requestingOffice,
                'Release the confirmed BAC Resolution back to the end user for RFQ preparation.'
            ),
            ProcurementDocument::STATUS_READY_FOR_RFQ => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_READY_FOR_RFQ,
                'rfq_draft',
                'RFQ Preparation',
                $requestingOffice,
                'Create the RFQ for supplier quotation.'
            ),
            ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW => $this->route(
                'Budget Office',
                ProcurementDocument::STAGE_BUDGET_REVIEW,
                ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
                ProcurementDocument::STAGE_BUDGET_REVIEW,
                'Budget Office',
                'Start budget availability review.'
            ),
            ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW => $this->route(
                'Budget Office',
                ProcurementDocument::STAGE_BUDGET_REVIEW,
                ProcurementDocument::STATUS_BUDGET_REVIEWED,
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                'Accounting Office',
                'Record budget result and forward compliant documents to Accounting.'
            ),
            ProcurementDocument::STATUS_BUDGET_REVIEWED => $this->route(
                'Accounting Office',
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                'Accounting Office',
                'Accounting should receive and begin verification.'
            ),
            ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW => $this->route(
                'Accounting Office',
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                'Accounting Office',
                'Start accounting verification.'
            ),
            ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW => $this->route(
                'Accounting Office',
                ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'BAC Secretariat',
                'Record accounting result and forward compliant documents to BAC Secretariat.'
            ),
            ProcurementDocument::STATUS_ACCOUNTING_REVIEWED => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'BAC Secretariat',
                'BAC Secretariat should receive the verified document.'
            ),
            ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'BAC Secretariat',
                'Acknowledge receipt for BAC Secretariat review.'
            ),
            ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'BAC Secretariat',
                'Start BAC Secretariat review.'
            ),
            ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                ProcurementDocument::STAGE_DOCUMENT_ROUTING,
                'BAC Secretariat',
                'Route the document to the next approving/reviewing body.'
            ),
            ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING => $this->route(
                'BAC Secretariat',
                ProcurementDocument::STAGE_DOCUMENT_ROUTING,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                'BAC Member',
                'Route the document to BAC Member review.'
            ),
            ProcurementDocument::STATUS_ROUTED_TO_BAC_MEMBER => $this->route(
                'BAC Member',
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                'BAC Member',
                'BAC Member should begin review.'
            ),
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW => $this->route(
                'BAC Member',
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                'BAC Member',
                'Start BAC Member review.'
            ),
            ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW => $this->route(
                'BAC Member',
                ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'BAC Chair',
                'Endorse to BAC Chair or return with remarks.'
            ),
            ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER => $this->route(
                'BAC Chair',
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'BAC Chair',
                'BAC Chair should receive the endorsed document.'
            ),
            ProcurementDocument::STATUS_ROUTED_TO_BAC_CHAIR => $this->route(
                'BAC Chair',
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'BAC Chair',
                'BAC Chair should begin review.'
            ),
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW => $this->route(
                'BAC Chair',
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'BAC Chair',
                'Start BAC Chair review.'
            ),
            ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW => $this->route(
                'BAC Chair',
                ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
                ProcurementDocument::STAGE_BAC_CHAIR_CONFIRMATION,
                'BAC Chair',
                'Confirm/sign or return the document.'
            ),
            ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION => $this->route(
                'BAC Chair',
                ProcurementDocument::STAGE_BAC_CHAIR_CONFIRMATION,
                ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'Head of the Procuring Entity',
                'Confirm the document and forward it to HOPE.'
            ),
            ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR => $this->route(
                'Head of the Procuring Entity',
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'Head of the Procuring Entity',
                'HOPE should receive the document for approval.'
            ),
            ProcurementDocument::STATUS_ROUTED_TO_APPROVING_AUTHORITY => $this->route(
                'Head of the Procuring Entity',
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'Head of the Procuring Entity',
                'HOPE should receive the document for approval.'
            ),
            ProcurementDocument::STATUS_PENDING_APPROVAL => $this->route(
                'Head of the Procuring Entity',
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                ProcurementDocument::STATUS_UNDER_APPROVAL,
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'Head of the Procuring Entity',
                'Start final approval review.'
            ),
            ProcurementDocument::STATUS_UNDER_APPROVAL => $this->route(
                'Head of the Procuring Entity',
                ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STAGE_APPROVED,
                $requestingOffice,
                'Approve or return the document with remarks.'
            ),
            ProcurementDocument::STATUS_READY_FOR_PO => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_READY_FOR_PURCHASE_ORDER,
                ProcurementDocument::STATUS_PO_DRAFT,
                ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                $requestingOffice,
                'Prepare the Purchase Order.'
            ),
            ProcurementDocument::STATUS_PO_DRAFT => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                ProcurementDocument::STATUS_PO_PREPARED,
                ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                $requestingOffice,
                'Complete and submit the Purchase Order.'
            ),
            ProcurementDocument::STATUS_PO_PREPARED => $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                ProcurementDocument::STATUS_PO_ISSUED,
                ProcurementDocument::STAGE_PURCHASE_ORDER_ISSUED,
                'Supplier / Requesting Office',
                'Issue the Purchase Order to the supplier.'
            ),
            ProcurementDocument::STATUS_PO_ISSUED => $this->route(
                'Supplier / Requesting Office',
                ProcurementDocument::STAGE_PURCHASE_ORDER_ISSUED,
                ProcurementDocument::STATUS_PO_COMPLETED,
                ProcurementDocument::STAGE_PURCHASE_ORDER_COMPLETED,
                $requestingOffice,
                'Complete delivery, inspection, and acceptance.'
            ),
        ];

        foreach ([
            ProcurementDocument::STATUS_APPROVED,
            ProcurementDocument::STATUS_APP_APPROVED,
            ProcurementDocument::STATUS_PO_APPROVED,
            ProcurementDocument::STATUS_PO_COMPLETED,
        ] as $terminalStatus) {
            $map[$terminalStatus] = $this->route(
                $this->currentHolderFor($document, $document) ?: $requestingOffice,
                ProcurementDocument::STAGE_APPROVED,
                null,
                null,
                null,
                'No further route is required unless a related next document must be prepared.',
                true
            );
        }

        foreach ([
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
        ] as $returnedStatus) {
            $map[$returnedStatus] = $this->route(
                $requestingOffice,
                ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                null,
                null,
                $requestingOffice,
                'Review the return remarks, correct the document, then resubmit through the proper workflow.'
            );
        }

        return $map[$status] ?? $this->fallbackRule($status);
    }

    private function standaloneRule(string $type, ?string $status): array
    {
        $rules = [
            'ppmp_record' => [
                Ppmp::STATUS_DRAFT => $this->route('End User Office', 'PPMP Draft', Ppmp::STATUS_SUBMITTED, 'BAC Secretariat PPMP Review', 'BAC Secretariat', 'Submit PPMP for BAC Secretariat review.'),
                Ppmp::STATUS_SUBMITTED => $this->route('BAC Secretariat', 'BAC Secretariat PPMP Review', Ppmp::STATUS_REVIEWED, 'BAC Secretariat PPMP Review', 'BAC Secretariat', 'Review the submitted PPMP.'),
                Ppmp::STATUS_REVIEWED => $this->route('BAC Secretariat', 'Reviewed PPMP', Ppmp::STATUS_APPROVED, 'APP Consolidation', 'BAC Secretariat APP Consolidation', 'Include the reviewed PPMP in APP consolidation.'),
                Ppmp::STATUS_APPROVED => $this->route('BAC Secretariat APP Consolidation', 'APP Consolidation', null, null, null, 'No further PPMP route is required.', true),
                Ppmp::STATUS_RETURNED => $this->route('End User Office', 'Returned PPMP', Ppmp::STATUS_SUBMITTED, 'BAC Secretariat PPMP Review', 'BAC Secretariat', 'Revise and resubmit the PPMP.'),
            ],
            'app' => [
                AnnualProcurementPlan::STATUS_DRAFT => $this->route('BAC Secretariat', 'APP Draft', AnnualProcurementPlan::STATUS_SUBMITTED, 'HOPE Approval', 'Head of the Procuring Entity', 'Submit APP to HOPE for approval.'),
                AnnualProcurementPlan::STATUS_CONSOLIDATED => $this->route('BAC Secretariat', 'APP Consolidated', AnnualProcurementPlan::STATUS_SUBMITTED, 'HOPE Approval', 'Head of the Procuring Entity', 'Submit consolidated APP to HOPE.'),
                AnnualProcurementPlan::STATUS_SUBMITTED => $this->route('Head of the Procuring Entity', 'HOPE Approval', AnnualProcurementPlan::STATUS_APPROVED, 'Approved APP', 'BAC Secretariat', 'HOPE should approve or return the APP.'),
                AnnualProcurementPlan::STATUS_APPROVED => $this->route('BAC Secretariat', 'Approved APP', null, null, null, 'APP is approved and ready for procurement reference.', true),
                AnnualProcurementPlan::STATUS_RETURNED => $this->route('BAC Secretariat', 'Returned APP', AnnualProcurementPlan::STATUS_SUBMITTED, 'HOPE Approval', 'Head of the Procuring Entity', 'Revise and resubmit APP.'),
            ],
            'supplemental_app' => [
                SupplementalApp::STATUS_DRAFT => $this->route('BAC Secretariat', 'Supplemental APP Draft', SupplementalApp::STATUS_SUBMITTED, 'BAC Secretariat Review', 'BAC Secretariat', 'Save and link, then submit Supplemental APP support.'),
                SupplementalApp::STATUS_CREATED => $this->route('BAC Secretariat', 'Supplemental APP Created', SupplementalApp::STATUS_SUBMITTED, 'BAC Secretariat Review', 'BAC Secretariat', 'Submit Supplemental APP support for acceptance.'),
                SupplementalApp::STATUS_SUBMITTED => $this->route('BAC Secretariat', 'Supplemental APP Review', SupplementalApp::STATUS_ACCEPTED, 'Linked to PR', 'BAC Secretariat', 'Accept and link Supplemental APP to the PR.'),
                SupplementalApp::STATUS_ACCEPTED => $this->route('BAC Secretariat', 'Accepted Supplemental APP', SupplementalApp::STATUS_LINKED_TO_PR, 'Ready for BAC Resolution', 'BAC Secretariat', 'Continue the source PR to BAC Resolution preparation.', true),
                SupplementalApp::STATUS_LINKED_TO_PR => $this->route('BAC Secretariat', 'Linked Supplemental APP', null, null, null, 'Supplemental APP is linked to the PR.', true),
            ],
            'bac_resolution' => [
                BacResolution::STATUS_DRAFT => $this->route('BAC Secretariat', 'BAC Resolution Draft', BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR, 'BAC Chair Confirmation', 'BAC Chair', 'Submit BAC Resolution to BAC Chair.'),
                BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR => $this->route('BAC Chair', 'BAC Chair Confirmation', BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR, 'BAC Chair Confirmed', 'BAC Secretariat', 'BAC Chair should confirm/sign or return the resolution.'),
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR => $this->route('BAC Secretariat', 'BAC Chair Confirmed', BacResolution::STATUS_FORWARDED_TO_HOPE, 'HOPE Approval', 'Head of the Procuring Entity', 'Forward confirmed resolution to HOPE if final approval is required.'),
                BacResolution::STATUS_FORWARDED_TO_HOPE => $this->route('Head of the Procuring Entity', 'HOPE Approval', BacResolution::STATUS_APPROVED_BY_HOPE, 'Approved Resolution', 'BAC Secretariat / End User', 'HOPE should approve or return the resolution.'),
                BacResolution::STATUS_APPROVED_BY_HOPE => $this->route('End User Office', 'Approved Resolution', null, null, null, 'Resolution is approved and can support the next SVP document.', true),
                BacResolution::STATUS_RETURNED_BY_BAC_CHAIR => $this->route('BAC Secretariat', 'Returned BAC Resolution', BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR, 'BAC Chair Confirmation', 'BAC Chair', 'Revise and resubmit to BAC Chair.'),
                BacResolution::STATUS_RETURNED_BY_HOPE => $this->route('BAC Secretariat', 'Returned by HOPE', BacResolution::STATUS_FORWARDED_TO_HOPE, 'HOPE Approval', 'Head of the Procuring Entity', 'Revise and resubmit to HOPE.'),
                BacResolution::STATUS_RETURNED_TO_END_USER => $this->route('End User Office', 'Returned to End User', null, null, null, 'End user should continue to RFQ when ready.', true),
            ],
            'rfq' => [
                Rfq::STATUS_DRAFT => $this->route('End User Office', 'RFQ Draft', Rfq::STATUS_SUBMITTED, 'RFQ Submission', 'End User Office', 'Submit or issue the RFQ for supplier quotation.'),
                Rfq::STATUS_SUBMITTED => $this->route('End User Office', 'RFQ Submitted', Rfq::STATUS_ISSUED, 'RFQ Issued', 'Supplier / End User Office', 'Issue RFQ to suppliers.'),
                Rfq::STATUS_ISSUED => $this->route('Supplier / End User Office', 'RFQ Issued', Rfq::STATUS_QUOTED, 'Quotation Received', 'End User Office', 'Record received quotations.'),
                Rfq::STATUS_QUOTED => $this->route('End User Office', 'Quotation Received', 'abstract_draft', 'Abstract Preparation', 'End User Office', 'Prepare Abstract of Quotations.'),
                Rfq::STATUS_RETURNED => $this->route('End User Office', 'Returned RFQ', Rfq::STATUS_SUBMITTED, 'RFQ Submission', 'End User Office', 'Correct and resubmit RFQ.'),
            ],
            'abstract' => [
                AbstractQuotation::STATUS_DRAFT => $this->route('End User Office', 'Abstract Draft', AbstractQuotation::STATUS_SUBMITTED, 'Abstract Submission', 'End User Office', 'Submit Abstract after encoding quotations.'),
                AbstractQuotation::STATUS_SUBMITTED => $this->route('End User Office', 'Abstract Submitted', AbstractQuotation::STATUS_READY_FOR_PO, 'Ready for Purchase Order', 'End User Office', 'Mark Abstract ready for PO preparation.'),
                AbstractQuotation::STATUS_READY_FOR_PO => $this->route('End User Office', 'Ready for PO', 'po_draft', 'Purchase Order Preparation', 'End User Office', 'Prepare Purchase Order.', true),
            ],
            'purchase_order' => [
                PurchaseOrder::STATUS_DRAFT => $this->route('End User Office', 'PO Draft', PurchaseOrder::STATUS_SUBMITTED, 'PO Submission', 'End User Office', 'Submit Purchase Order for processing.'),
                PurchaseOrder::STATUS_SUBMITTED => $this->route('End User Office', 'PO Submitted', PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER, 'Forwarded to Supplier', 'Supplier', 'Forward PO to the supplier.'),
                PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER => $this->route('Supplier', 'Forwarded to Supplier', PurchaseOrder::STATUS_FUND_CERTIFIED, 'Fund Certification', 'Accounting Office', 'Record fund certification if required.'),
                PurchaseOrder::STATUS_FUND_CERTIFIED => $this->route('Accounting Office', 'Fund Certified', PurchaseOrder::STATUS_APPROVED, 'PO Approved', 'Head of the Procuring Entity', 'Approve Purchase Order.'),
                PurchaseOrder::STATUS_APPROVED => $this->route('End User Office', 'PO Approved', PurchaseOrder::STATUS_ISSUED, 'PO Issued', 'Supplier / End User Office', 'Issue approved Purchase Order.'),
                PurchaseOrder::STATUS_ISSUED => $this->route('Supplier / End User Office', 'PO Issued', PurchaseOrder::STATUS_COMPLETED, 'Inspection / Acceptance', 'End User Office', 'Proceed to inspection and acceptance.', true),
                PurchaseOrder::STATUS_COMPLETED => $this->route('End User Office', 'PO Completed', null, null, null, 'Purchase Order workflow is complete.', true),
            ],
            'inspection_acceptance' => [
                InspectionAcceptanceRecord::STATUS_DRAFT => $this->route('End User Office', 'Inspection Draft', InspectionAcceptanceRecord::STATUS_INSPECTED, 'Inspection', 'Inspection / Acceptance Team', 'Record inspection details.'),
                InspectionAcceptanceRecord::STATUS_INSPECTED => $this->route('Inspection / Acceptance Team', 'Inspected', InspectionAcceptanceRecord::STATUS_ACCEPTED, 'Acceptance', 'End User Office', 'Record acceptance details.'),
                InspectionAcceptanceRecord::STATUS_ACCEPTED => $this->route('End User Office', 'Accepted', InspectionAcceptanceRecord::STATUS_COMPLETED, 'Completed', 'End User Office', 'Complete the inspection and acceptance record.'),
                InspectionAcceptanceRecord::STATUS_COMPLETED => $this->route('End User Office', 'Completed', null, null, null, 'Inspection and acceptance workflow is complete.', true),
            ],
        ];

        return $rules[$type][$status] ?? $this->fallbackRule($status);
    }

    private function aiExplanation(array $context, array $validation): ?string
    {
        try {
            $payload = [
                'document_type' => $context['document_type'],
                'tracking_number' => $context['tracking_number'],
                'current_status' => $context['current_status_label'],
                'current_stage' => $context['current_stage'],
                'current_holder' => $context['current_holder'],
                'expected_next_stage' => $context['expected_next_stage'],
                'expected_next_holder' => $context['expected_next_holder'],
                'next_action' => $context['next_action'],
                'warnings' => $validation['warnings'],
                'route_status' => $validation['status'],
            ];

            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are PaperTrail AI Route Validation Assistant. The provided Laravel workflow data is the official source of truth. Do not invent statuses, offices, permissions, approvals, or route decisions. Return only JSON with keys message and recommendation.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                ],
            ], [
                'temperature' => 0,
                'max_tokens' => 220,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = json_decode($response, true);
            $message = trim((string) data_get($parsed, 'message', ''));

            return $message !== '' ? $message : null;
        } catch (Throwable $exception) {
            Log::warning('ai_route_validation_openai_unavailable_using_rules', [
                'document_type' => $context['document_type_key'],
                'document_id' => $context['document_id'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fallbackExplanation(array $context, array $validation): string
    {
        if ($validation['status'] === 'complete') {
            return 'This document is already in a completed or closed workflow state. No automatic routing action is required.';
        }

        if ($validation['warnings'] !== []) {
            return 'PaperTrail found route items that need manual review before this document is moved to the next step.';
        }

        $next = $context['expected_next_holder'] ?: $context['expected_next_stage'] ?: 'the next workflow step';

        return "The current route looks consistent. The next expected handler is {$next}.";
    }

    private function recommendationFor(array $context, array $warnings): string
    {
        if ($context['terminal']) {
            return $context['next_action'] ?: 'No further route validation action is needed.';
        }

        if ($warnings !== []) {
            return 'Review the warnings, confirm the current holder, and check any remarks before routing this document.';
        }

        return $context['next_action'] ?: 'Continue with the next configured workflow action.';
    }

    private function route(
        ?string $currentHolder,
        ?string $currentStage,
        ?string $nextStatus,
        ?string $nextStage,
        ?string $nextHolder,
        ?string $nextAction,
        bool $terminal = false,
        bool $requiresSignature = false,
        bool $requiresAttachmentCheck = false
    ): array {
        return [
            'source' => 'built_in_workflow',
            'expected_current_holder' => $currentHolder,
            'expected_current_stage' => $currentStage,
            'expected_next_status' => $nextStatus,
            'expected_next_stage' => $nextStage,
            'expected_next_holder' => $nextHolder,
            'next_action' => $nextAction,
            'requires_signature' => $requiresSignature,
            'requires_attachment_check' => $requiresAttachmentCheck,
            'terminal' => $terminal,
        ];
    }

    private function fallbackRule(?string $status): array
    {
        return [
            'source' => 'fallback',
            'expected_current_holder' => null,
            'expected_current_stage' => $this->humanizeStatus($status),
            'expected_next_status' => null,
            'expected_next_stage' => null,
            'expected_next_holder' => null,
            'next_action' => 'Check the route manually because this workflow status is not yet mapped in Phase 1.',
            'requires_signature' => false,
            'requires_attachment_check' => false,
            'terminal' => false,
        ];
    }

    private function check(string $label, string $status, string $detail): array
    {
        return compact('label', 'status', 'detail');
    }

    private function relationshipsFor(Model $document): array
    {
        $relations = [
            'submittingOffice',
            'currentOffice',
            'assignedTo',
            'routeDestinationOffice',
            'submittedBy',
            'preparedBy',
            'office',
            'creator',
            'createdBy',
            'preparedBy',
            'approvedBy',
            'submittedBy',
            'sourcePrDocument',
            'procurementDocument',
            'purchaseOrder',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ];

        return array_values(array_filter($relations, function (string $relation) use ($document) {
            $root = Str::before($relation, '.');

            return method_exists($document, $root);
        }));
    }

    private function historyFor(Model $document, ?ProcurementDocument $source): array
    {
        $historyDocument = $source ?: ($document instanceof ProcurementDocument ? $document : null);

        if ($historyDocument) {
            $historyDocument->loadMissing([
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ]);

            $history = $historyDocument->routingHistories
                ->sortBy('action_at')
                ->values()
                ->map(function ($item) {
                    return [
                        'action' => $item->action ?: 'Workflow action',
                        'status_from' => $this->humanizeStatus($item->status_from),
                        'status_to' => $this->humanizeStatus($item->status_to),
                        'date' => $this->formatDate($item->action_at),
                        'actor' => $item->actionBy?->name,
                        'from' => $item->fromOffice?->name,
                        'to' => $item->toOffice?->name,
                        'comments' => Str::limit((string) $item->comments, 160),
                    ];
                })
                ->all();

            if ($history !== []) {
                return $history;
            }
        }

        return $this->fallbackHistory($document);
    }

    private function fallbackHistory(Model $document): array
    {
        $events = [];

        foreach ([
            'created_at' => 'Document Created',
            'submitted_at' => 'Document Submitted',
            'returned_at' => 'Document Returned',
            'approved_at' => 'Document Approved',
            'completed_at' => 'Document Completed',
            'updated_at' => 'Last Updated',
        ] as $attribute => $label) {
            if (filled($document->{$attribute} ?? null)) {
                $events[] = [
                    'action' => $label,
                    'status_from' => null,
                    'status_to' => null,
                    'date' => $this->formatDate($document->{$attribute}),
                    'actor' => null,
                    'from' => null,
                    'to' => null,
                    'comments' => null,
                ];
            }
        }

        return $events;
    }

    private function sourceProcurementDocument(Model $document): ?ProcurementDocument
    {
        if ($document instanceof ProcurementDocument) {
            return $document;
        }

        foreach (['sourcePrDocument', 'procurementDocument'] as $relation) {
            if (! method_exists($document, $relation)) {
                continue;
            }

            $document->loadMissing($relation);

            if ($document->{$relation} instanceof ProcurementDocument) {
                return $document->{$relation};
            }
        }

        if ($document instanceof InspectionAcceptanceRecord && method_exists($document, 'purchaseOrder')) {
            $document->loadMissing('purchaseOrder.sourcePrDocument', 'purchaseOrder.procurementDocument');

            return $document->purchaseOrder?->sourcePrDocument ?: $document->purchaseOrder?->procurementDocument;
        }

        return null;
    }

    private function currentHolderFor(Model $document, ?ProcurementDocument $source): ?string
    {
        $current = $document instanceof ProcurementDocument ? $document : $source;

        if ($current) {
            $current->loadMissing('assignedTo', 'currentOffice', 'routeDestinationOffice', 'submittingOffice');

            if ($current->assignedTo) {
                return trim($current->assignedTo->name . ' (' . $this->roleLabel($current->assignedTo->role) . ')');
            }

            if ($current->currentOffice) {
                return $current->currentOffice->name;
            }

            if ($current->routeDestinationOffice) {
                return $current->routeDestinationOffice->name;
            }

            if ($current->submittingOffice) {
                return $current->submittingOffice->name;
            }
        }

        foreach (['office', 'createdBy', 'preparedBy', 'submittedBy', 'approvedBy'] as $relation) {
            if (! method_exists($document, $relation)) {
                continue;
            }

            $document->loadMissing($relation);
            $related = $document->{$relation};

            if ($relation === 'office' && filled($related?->name)) {
                return $related->name;
            }

            if ($related instanceof User) {
                return trim($related->name . ' (' . $this->roleLabel($related->role) . ')');
            }
        }

        return $this->documents->officeNameFor($document);
    }

    private function statusFor(Model $document): ?string
    {
        foreach (['status', 'pr_status', 'signature_status'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (string) $document->{$attribute};
            }
        }

        return null;
    }

    private function stageFor(Model $document): ?string
    {
        foreach (['stage', 'current_stage', 'workflow_stage'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (string) $document->{$attribute};
            }
        }

        return null;
    }

    private function titleFor(Model $document): string
    {
        foreach (['title', 'project_title', 'purpose', 'description', 'supplier_name'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return Str::limit((string) $document->{$attribute}, 120);
            }
        }

        return class_basename($document) . ' #' . $document->getKey();
    }

    private function canonicalType(string $documentType): string
    {
        $type = $this->documents->normalizeDocumentType($documentType);

        return match ($type) {
            'pr', 'purchase_requests' => 'purchase_request',
            'ppmp_document', 'procurement_document_ppmp' => 'ppmp',
            'ppmps', 'standalone_ppmp' => 'ppmp_record',
            'annual_procurement_plan', 'annual_procurement_plans', 'apps' => 'app',
            'supplemental_apps', 'supplemental_annual_procurement_plan' => 'supplemental_app',
            'po', 'purchase_orders' => 'purchase_order',
            'rfqs', 'request_for_quotation', 'request_for_quotations' => 'rfq',
            'abstracts', 'abstract_quotation', 'abstract_quotations' => 'abstract',
            'inspection', 'inspection_acceptance_record', 'inspection_acceptance_records' => 'inspection_acceptance',
            'bac_resolution', 'bac_resolutions', 'resolution' => 'bac_resolution',
            default => $type,
        };
    }

    private function ruleDocumentTypeCandidates(string $type): array
    {
        $map = [
            'ppmp' => ['ppmp', 'PPMP'],
            'ppmp_record' => ['ppmp_record', 'ppmp', 'PPMP'],
            'purchase_request' => ['purchase_request', 'PR', 'Purchase Request'],
            'app' => ['app', 'APP', 'annual_procurement_plan'],
            'supplemental_app' => ['supplemental_app', 'Supplemental APP'],
            'bac_resolution' => ['bac_resolution', 'BAC Resolution'],
            'rfq' => ['rfq', 'RFQ'],
            'abstract' => ['abstract', 'Abstract', 'abstract_quotation'],
            'purchase_order' => ['purchase_order', 'PO', 'Purchase Order'],
            'inspection_acceptance' => ['inspection_acceptance', 'Inspection / Acceptance'],
        ];

        return array_values(array_unique($map[$type] ?? [$type]));
    }

    private function typeLabel(string $type, Model $document): string
    {
        if (method_exists($document, 'documentType')) {
            return (string) $document->documentType();
        }

        return match ($type) {
            'ppmp', 'ppmp_record' => 'Project Procurement Management Plan',
            'purchase_request' => 'Purchase Request',
            'app' => 'Annual Procurement Plan',
            'supplemental_app' => 'Supplemental APP',
            'bac_resolution' => 'BAC Resolution',
            'rfq' => 'Request for Quotation',
            'abstract' => 'Abstract of Quotations',
            'purchase_order' => 'Purchase Order',
            'inspection_acceptance' => 'Inspection / Acceptance',
            default => Str::title(str_replace('_', ' ', $type)),
        };
    }

    private function humanizeStatus(?string $status): ?string
    {
        if (! filled($status)) {
            return null;
        }

        $label = Str::of($status)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        $label = preg_replace('/\bPpmp\b/', 'PPMP', $label);
        $label = preg_replace('/\bPr\b/', 'PR', $label);
        $label = preg_replace('/\bPo\b/', 'PO', $label);
        $label = preg_replace('/\bBac\b/', 'BAC', $label);
        $label = preg_replace('/\bRfq\b/', 'RFQ', $label);
        $label = preg_replace('/\bApp\b/', 'APP', $label);
        $label = preg_replace('/\bHope\b/', 'HOPE', $label);

        return $label;
    }

    private function roleLabel(?string $role): ?string
    {
        if (! filled($role)) {
            return null;
        }

        return match ($role) {
            User::ROLE_HEAD_OFFICE, 'head_office' => 'Head of Office / End User',
            User::ROLE_BAC_SECRETARIAT, 'bac_secretariat' => 'BAC Secretariat',
            User::ROLE_BAC_MEMBER, 'bac_member' => 'BAC Member',
            User::ROLE_BAC_CHAIR, 'bac_chair' => 'BAC Chair',
            User::ROLE_BUDGET, 'budget_officer', 'budget' => 'Budget Office',
            User::ROLE_ACCOUNTING, 'accounting_officer', 'accounting' => 'Accounting Office',
            User::ROLE_APPROVING_AUTHORITY, 'approving_authority' => 'Head of the Procuring Entity',
            User::ROLE_PR_NUMBERING, 'pr_numbering_staff', 'pr-numbering' => 'PR Numbering Staff',
            User::ROLE_ADMIN, 'admin' => 'Admin',
            default => Str::title(str_replace(['_', '-'], ' ', $role)),
        };
    }

    private function formatDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        if (method_exists($value, 'format')) {
            return $value->format('M d, Y h:i A');
        }

        return (string) $value;
    }
}
