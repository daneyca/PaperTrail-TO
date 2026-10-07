<?php

namespace App\Services;

use App\Models\AiAssistRule;
use App\Models\DelayThresholdRule;
use App\Models\DocumentRequirementRule;
use App\Models\NotificationTemplate;
use App\Models\RoutingRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RuleConfigurationService
{
    public function getRequirementsForDocument(?string $documentType, ?string $status = null): Collection
    {
        if (! $documentType || ! Schema::hasTable('document_requirement_rules')) {
            return collect();
        }

        return DocumentRequirementRule::query()
            ->where('is_active', true)
            ->where('document_type', $documentType)
            ->when($status, function ($query) use ($status) {
                $query->where(function ($statusQuery) use ($status) {
                    $statusQuery->whereNull('applies_to_status')
                        ->orWhere('applies_to_status', $status);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('rule_name')
            ->get();
    }

    public function getAttachmentRequirements(?string $documentType, ?string $status = null): Collection
    {
        return $this->getRequirementsForDocument($documentType, $status)
            ->where('requirement_type', DocumentRequirementRule::TYPE_ATTACHMENT)
            ->values();
    }

    public function getAiRules(?string $featureType, ?string $documentType = null): Collection
    {
        if (! Schema::hasTable('ai_assist_rules')) {
            return collect();
        }

        return AiAssistRule::query()
            ->where('is_active', true)
            ->when($featureType, fn ($query) => $query->where('feature_type', $featureType))
            ->when($documentType, fn ($query) => $query->where('document_type', $documentType))
            ->orderBy('sort_order')
            ->orderBy('rule_name')
            ->get();
    }

    public function getRoutingRules(?string $documentType, ?string $currentStatus = null): Collection
    {
        if (! Schema::hasTable('routing_rules')) {
            return collect();
        }

        return RoutingRule::query()
            ->where('is_active', true)
            ->when($documentType, fn ($query) => $query->where('document_type', $documentType))
            ->when($currentStatus, function ($query) use ($currentStatus) {
                $query->where(function ($statusQuery) use ($currentStatus) {
                    $statusQuery->whereNull('current_status')
                        ->orWhere('current_status', $currentStatus);
                });
            })
            ->with(['fromOffice', 'toOffice'])
            ->orderBy('sort_order')
            ->orderBy('route_label')
            ->get();
    }

    public function getDelayThreshold(?string $documentType, ?string $stage = null, ?string $status = null): ?DelayThresholdRule
    {
        if (! Schema::hasTable('delay_threshold_rules')) {
            return null;
        }

        return DelayThresholdRule::query()
            ->where('is_active', true)
            ->when($documentType, fn ($query) => $query->where('document_type', $documentType))
            ->when($stage, function ($query) use ($stage) {
                $query->where(function ($stageQuery) use ($stage) {
                    $stageQuery->whereNull('stage')
                        ->orWhere('stage', $stage);
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where(function ($statusQuery) use ($status) {
                    $statusQuery->whereNull('status')
                        ->orWhere('status', $status);
                });
            })
            ->orderByDesc('status')
            ->orderByDesc('stage')
            ->first();
    }

    public function getNotificationTemplate(?string $templateKey): ?NotificationTemplate
    {
        if (! $templateKey || ! Schema::hasTable('notification_templates')) {
            return null;
        }

        return NotificationTemplate::query()
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();
    }

    public function renderNotificationTemplate(string $templateKey, array $data): array
    {
        $template = $this->getNotificationTemplate($templateKey);

        if (! $template) {
            return [
                'subject' => null,
                'body' => null,
                'action_text' => null,
                'channel' => null,
            ];
        }

        return [
            'subject' => $this->replacePlaceholders($template->subject, $data),
            'body' => $this->replacePlaceholders($template->body, $data),
            'action_text' => $this->replacePlaceholders($template->action_text, $data),
            'channel' => $template->channel,
        ];
    }

    private function replacePlaceholders(?string $value, array $data): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($data as $key => $replacement) {
            $value = str_replace('{' . $key . '}', (string) $replacement, $value);
        }

        return $value;
    }
}
