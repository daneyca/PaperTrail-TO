<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAssistRule;
use App\Models\DelayThresholdRule;
use App\Models\DocumentRequirementRule;
use App\Models\NotificationTemplate;
use App\Models\Office;
use App\Models\Role;
use App\Models\RoutingRule;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RuleConfigurationController extends Controller
{
    private const DOCUMENT_TYPES = [
        'ppmp' => 'PPMP',
        'app' => 'APP',
        'supplemental_app' => 'Supplemental APP',
        'purchase_request' => 'Purchase Request',
        'bac_resolution' => 'BAC Resolution',
        'rfq' => 'RFQ',
        'abstract' => 'Abstract',
        'purchase_order' => 'Purchase Order',
        'inspection_acceptance' => 'Inspection / Acceptance',
    ];

    public function aiRules(Request $request): View
    {
        return $this->indexFor('ai_rules', $request);
    }

    public function storeAiRule(Request $request): RedirectResponse
    {
        return $this->storeFor('ai_rules', $request);
    }

    public function updateAiRule(Request $request, AiAssistRule $rule): RedirectResponse
    {
        return $this->updateFor('ai_rules', $request, $rule);
    }

    public function destroyAiRule(AiAssistRule $rule): RedirectResponse
    {
        return $this->destroyFor('ai_rules', $rule);
    }

    public function documentRequirements(Request $request): View
    {
        return $this->indexFor('document_requirements', $request);
    }

    public function storeDocumentRequirement(Request $request): RedirectResponse
    {
        return $this->storeFor('document_requirements', $request);
    }

    public function updateDocumentRequirement(Request $request, DocumentRequirementRule $requirement): RedirectResponse
    {
        return $this->updateFor('document_requirements', $request, $requirement);
    }

    public function destroyDocumentRequirement(DocumentRequirementRule $requirement): RedirectResponse
    {
        return $this->destroyFor('document_requirements', $requirement);
    }

    public function routingRules(Request $request): View
    {
        return $this->indexFor('routing_rules', $request);
    }

    public function storeRoutingRule(Request $request): RedirectResponse
    {
        return $this->storeFor('routing_rules', $request);
    }

    public function updateRoutingRule(Request $request, RoutingRule $rule): RedirectResponse
    {
        return $this->updateFor('routing_rules', $request, $rule);
    }

    public function destroyRoutingRule(RoutingRule $rule): RedirectResponse
    {
        return $this->destroyFor('routing_rules', $rule);
    }

    public function delayThresholds(Request $request): View
    {
        return $this->indexFor('delay_thresholds', $request);
    }

    public function storeDelayThreshold(Request $request): RedirectResponse
    {
        return $this->storeFor('delay_thresholds', $request);
    }

    public function updateDelayThreshold(Request $request, DelayThresholdRule $threshold): RedirectResponse
    {
        return $this->updateFor('delay_thresholds', $request, $threshold);
    }

    public function destroyDelayThreshold(DelayThresholdRule $threshold): RedirectResponse
    {
        return $this->destroyFor('delay_thresholds', $threshold);
    }

    public function notificationTemplates(Request $request): View
    {
        return $this->indexFor('notification_templates', $request);
    }

    public function storeNotificationTemplate(Request $request): RedirectResponse
    {
        return $this->storeFor('notification_templates', $request);
    }

    public function updateNotificationTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        return $this->updateFor('notification_templates', $request, $template);
    }

    public function destroyNotificationTemplate(NotificationTemplate $template): RedirectResponse
    {
        return $this->destroyFor('notification_templates', $template);
    }

    private function indexFor(string $key, Request $request): View
    {
        $config = $this->config($key);
        $query = ($config['model'])::query();

        if ($key === 'routing_rules') {
            $query->with(['fromOffice', 'toOffice']);
        }

        if ($key === 'delay_thresholds') {
            $query->with(['notifyOffice']);
        }

        $query->when($request->filled('search'), function ($builder) use ($request, $config) {
            $search = $request->string('search')->toString();
            $builder->where(function ($nested) use ($search, $config) {
                foreach ($config['search'] as $field) {
                    $nested->orWhere($field, 'like', "%{$search}%");
                }
            });
        });

        AuditLogger::log('Rule Configuration', $config['audit_viewed'], "Admin viewed {$config['title']}.");

        $base = ($config['model'])::query();

        return view('admin.settings.rule-config.index', [
            'config' => $config,
            'records' => $query->orderBy($config['order_by'])->paginate(10)->withQueryString(),
            'summary' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('is_active', true)->count(),
                'inactive' => (clone $base)->where('is_active', false)->count(),
            ],
            'filters' => $request->only(['search']),
            'options' => $this->options(),
        ]);
    }

    private function storeFor(string $key, Request $request): RedirectResponse
    {
        $config = $this->config($key);
        $validated = $this->validated($key, $request);

        DB::transaction(function () use ($request, $config, $validated) {
            $record = ($config['model'])::create(array_merge($validated, [
                'created_by_user_id' => $request->user()->id,
            ]));

            AuditLogger::log('Rule Configuration', $config['audit_created'], "{$config['singular']} created.", $record);
        });

        return back()->with('status', "{$config['singular']} created.");
    }

    private function updateFor(string $key, Request $request, Model $record): RedirectResponse
    {
        $config = $this->config($key);
        $validated = $this->validated($key, $request, $record);
        $old = $record->getOriginal();

        DB::transaction(function () use ($config, $record, $validated, $old) {
            $record->update($validated);
            AuditLogger::log('Rule Configuration', $config['audit_updated'], "{$config['singular']} updated.", $record, $old, $record->fresh()->toArray());
        });

        return back()->with('status', "{$config['singular']} updated.");
    }

    private function destroyFor(string $key, Model $record): RedirectResponse
    {
        $config = $this->config($key);

        DB::transaction(function () use ($config, $record) {
            AuditLogger::log('Rule Configuration', $config['audit_deleted'], "{$config['singular']} deleted.", $record, $record->toArray(), null, 'warning');
            $record->delete();
        });

        return back()->with('status', "{$config['singular']} deleted.");
    }

    private function validated(string $key, Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();

        $rules = match ($key) {
            'document_requirements' => [
                'document_type' => ['nullable', 'string', 'max:120'],
                'rule_name' => ['required', 'string', 'max:255'],
                'requirement_type' => ['required', Rule::in(['field', 'attachment', 'amount', 'schedule', 'signatory', 'workflow'])],
                'field_key' => ['nullable', 'string', 'max:120'],
                'attachment_category' => ['nullable', 'string', 'max:120'],
                'description' => ['nullable', 'string', 'max:5000'],
                'is_required' => ['nullable', 'boolean'],
                'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
                'applies_to_status' => ['nullable', 'string', 'max:120'],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'ai_rules' => [
                'feature_type' => ['required', Rule::in(['completeness_check', 'routing_suggestion', 'metadata_extraction', 'classification', 'delay_risk', 'anomaly_detection', 'chatbot'])],
                'document_type' => ['nullable', 'string', 'max:120'],
                'rule_name' => ['required', 'string', 'max:255'],
                'prompt_instruction' => ['nullable', 'string', 'max:10000'],
                'expected_output_schema' => ['nullable', 'json'],
                'system_note' => ['nullable', 'string', 'max:5000'],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'routing_rules' => [
                'document_type' => ['nullable', 'string', 'max:120'],
                'current_status' => ['nullable', 'string', 'max:120'],
                'next_status' => ['nullable', 'string', 'max:120'],
                'from_role' => ['nullable', 'string', 'max:120'],
                'to_role' => ['nullable', 'string', 'max:120'],
                'from_office_id' => ['nullable', 'exists:offices,id'],
                'to_office_id' => ['nullable', 'exists:offices,id'],
                'route_label' => ['required', 'string', 'max:255'],
                'rule_description' => ['nullable', 'string', 'max:5000'],
                'requires_signature' => ['nullable', 'boolean'],
                'requires_attachment_check' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'delay_thresholds' => [
                'document_type' => ['nullable', 'string', 'max:120'],
                'stage' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'max:120'],
                'low_risk_days' => ['required', 'integer', 'min:0', 'max:365'],
                'medium_risk_days' => ['required', 'integer', 'min:0', 'max:365'],
                'high_risk_days' => ['required', 'integer', 'min:0', 'max:365'],
                'critical_risk_days' => ['required', 'integer', 'min:0', 'max:365'],
                'notify_role' => ['nullable', 'string', 'max:120'],
                'notify_office_id' => ['nullable', 'exists:offices,id'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'notification_templates' => [
                'template_key' => ['required', 'string', 'max:160', Rule::unique('notification_templates', 'template_key')->ignore($id)],
                'channel' => ['required', Rule::in(['email', 'in_app', 'both'])],
                'subject' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string'],
                'action_text' => ['nullable', 'string', 'max:120'],
                'is_active' => ['nullable', 'boolean'],
            ],
        };

        $validated = $request->validate($rules);

        foreach (['is_required', 'is_active', 'requires_signature', 'requires_attachment_check'] as $booleanField) {
            if (array_key_exists($booleanField, $rules)) {
                $validated[$booleanField] = $request->boolean($booleanField);
            }
        }

        if ($key === 'ai_rules') {
            $validated['expected_output_schema'] = filled($validated['expected_output_schema'] ?? null)
                ? json_decode($validated['expected_output_schema'], true)
                : null;
        }

        if (array_key_exists('sort_order', $rules)) {
            $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        }

        return $validated;
    }

    private function options(): array
    {
        return [
            'documentTypes' => self::DOCUMENT_TYPES,
            'requirementTypes' => [
                'field' => 'Field',
                'attachment' => 'Attachment',
                'amount' => 'Amount',
                'schedule' => 'Schedule',
                'signatory' => 'Signatory',
                'workflow' => 'Workflow',
            ],
            'attachmentCategories' => [
                'supporting_document' => 'Supporting Document',
                'scanned_document' => 'Scanned Document',
                'quotation' => 'Quotation',
                'eligibility_document' => 'Eligibility Document',
                'bac_document' => 'BAC Document',
                'signed_document' => 'Signed Document',
                'other' => 'Other',
            ],
            'severities' => [
                'info' => 'Info',
                'warning' => 'Warning',
                'critical' => 'Critical',
            ],
            'featureTypes' => [
                'completeness_check' => 'Completeness Check',
                'routing_suggestion' => 'Routing Suggestion',
                'metadata_extraction' => 'Metadata Extraction',
                'classification' => 'Classification',
                'delay_risk' => 'Delay Risk',
                'anomaly_detection' => 'Anomaly Detection',
                'chatbot' => 'Chatbot',
            ],
            'channels' => [
                'email' => 'Email',
                'in_app' => 'In-app',
                'both' => 'Both',
            ],
            'roles' => Role::orderBy('name')->pluck('name', 'name')->all(),
            'offices' => Office::orderBy('name')->get(),
        ];
    }

    private function config(string $key): array
    {
        return [
            'ai_rules' => [
                'title' => 'AI Rules',
                'singular' => 'AI rule',
                'route_prefix' => 'admin.settings.ai-rules',
                'model' => AiAssistRule::class,
                'order_by' => 'sort_order',
                'search' => ['feature_type', 'document_type', 'rule_name', 'prompt_instruction'],
                'audit_viewed' => 'ai_assist_rules_viewed',
                'audit_created' => 'ai_assist_rule_created',
                'audit_updated' => 'ai_assist_rule_updated',
                'audit_deleted' => 'ai_assist_rule_deleted',
                'description' => 'Store AI-ready prompt rules and schemas. No OpenAI calls are made here.',
                'fields' => ['feature_type', 'document_type', 'rule_name', 'prompt_instruction', 'expected_output_schema', 'system_note', 'sort_order', 'is_active'],
            ],
            'document_requirements' => [
                'title' => 'Document Requirements',
                'singular' => 'document requirement rule',
                'route_prefix' => 'admin.settings.document-requirements',
                'model' => DocumentRequirementRule::class,
                'order_by' => 'sort_order',
                'search' => ['document_type', 'rule_name', 'requirement_type', 'field_key', 'attachment_category', 'description'],
                'audit_viewed' => 'document_requirement_rules_viewed',
                'audit_created' => 'document_requirement_rule_created',
                'audit_updated' => 'document_requirement_rule_updated',
                'audit_deleted' => 'document_requirement_rule_deleted',
                'description' => 'Manage field, attachment, signatory, and workflow requirements per document type.',
                'fields' => ['document_type', 'requirement_type', 'rule_name', 'field_key', 'attachment_category', 'description', 'applies_to_status', 'severity', 'sort_order', 'is_required', 'is_active'],
            ],
            'routing_rules' => [
                'title' => 'Routing Rules',
                'singular' => 'routing rule',
                'route_prefix' => 'admin.settings.routing-rules',
                'model' => RoutingRule::class,
                'order_by' => 'sort_order',
                'search' => ['document_type', 'current_status', 'next_status', 'from_role', 'to_role', 'route_label', 'rule_description'],
                'audit_viewed' => 'routing_rules_viewed',
                'audit_created' => 'routing_rule_created',
                'audit_updated' => 'routing_rule_updated',
                'audit_deleted' => 'routing_rule_deleted',
                'description' => 'Configure rule-based routing suggestions for procurement document movement.',
                'fields' => ['document_type', 'current_status', 'next_status', 'from_role', 'to_role', 'from_office_id', 'to_office_id', 'route_label', 'rule_description', 'sort_order', 'requires_signature', 'requires_attachment_check', 'is_active'],
            ],
            'delay_thresholds' => [
                'title' => 'Delay Thresholds',
                'singular' => 'delay threshold rule',
                'route_prefix' => 'admin.settings.delay-thresholds',
                'model' => DelayThresholdRule::class,
                'order_by' => 'document_type',
                'search' => ['document_type', 'stage', 'status', 'notify_role'],
                'audit_viewed' => 'delay_threshold_rules_viewed',
                'audit_created' => 'delay_threshold_rule_created',
                'audit_updated' => 'delay_threshold_updated',
                'audit_deleted' => 'delay_threshold_rule_deleted',
                'description' => 'Set rule-based delay risk days by document type, stage, and status.',
                'fields' => ['document_type', 'stage', 'status', 'low_risk_days', 'medium_risk_days', 'high_risk_days', 'critical_risk_days', 'notify_role', 'notify_office_id', 'is_active'],
            ],
            'notification_templates' => [
                'title' => 'Notification Templates',
                'singular' => 'notification template',
                'route_prefix' => 'admin.settings.notification-templates',
                'model' => NotificationTemplate::class,
                'order_by' => 'template_key',
                'search' => ['template_key', 'channel', 'subject', 'body', 'action_text'],
                'audit_viewed' => 'notification_templates_viewed',
                'audit_created' => 'notification_template_created',
                'audit_updated' => 'notification_template_updated',
                'audit_deleted' => 'notification_template_deleted',
                'description' => 'Manage reusable in-app and email notification wording with placeholders.',
                'fields' => ['template_key', 'channel', 'subject', 'body', 'action_text', 'is_active'],
            ],
        ][$key];
    }
}
