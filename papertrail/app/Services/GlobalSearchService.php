<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\BacResolution;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SvpProcurementChain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function search(User $user, string $term, int $limit = 30): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $limit = max(1, min($limit, 30));
        $matches = collect();

        foreach ($this->sources($user) as [$query, $fields, $type, $route, $number, $title, $status]) {
            $this->matchFields($query, $fields, $term);
            $columns = array_unique(array_merge(['id', 'updated_at'], $fields, [$title, $status]));

            foreach ($query->select($columns)->orderByDesc('updated_at')->limit($limit)->get() as $record) {
                $matches->push([
                    'key' => get_class($record) . ':' . $record->getKey(),
                    'type' => $type === 'Document' ? (string) $record->document_type : $type,
                    'reference' => $number === 'display_number' ? $record->displayNumber() : (string) $record->{$number},
                    'title' => (string) ($record->{$title} ?: ($number === 'display_number' ? $record->displayNumber() : $record->{$number})),
                    'status' => (string) $record->{$status},
                    'url' => route($route, $record),
                    'updated_at' => $record->updated_at,
                ]);
            }
        }

        return $matches->sortByDesc('updated_at')->unique('key')->take($limit)->values();
    }

    private function matchFields(Builder $query, array $fields, string $term): void
    {
        $query->where(function (Builder $matches) use ($fields, $term) {
            foreach ($fields as $field) {
                $matches->orWhere($field, 'like', '%' . $term . '%');
            }
        });
    }

    private function sources(User $user): array
    {
        $sources = [];
        $documentFields = ['tracking_number', 'document_reference_number', 'pr_no', 'ppmp_no', 'document_type', 'title', 'description'];

        $addDocument = function (Builder $query, string $route) use (&$sources, $documentFields): void {
            $sources[] = [$query, $documentFields, 'Document', $route, 'display_number', 'title', 'status'];
        };

        switch ($user->roleSlug()) {
            case 'head-office':
                $addDocument(ProcurementDocument::query()->where(function (Builder $query) use ($user) {
                    if ($user->office_id) {
                        $query->where('submitting_office_id', $user->office_id);
                    }
                    $query->orWhere('submitted_by_user_id', $user->id)
                        ->orWhere('prepared_by_user_id', $user->id);
                }), 'head-office.documents.show');
                $this->addProcurementSources($sources, $user, true);
                break;
            case 'bac-secretariat':
                $addDocument(ProcurementDocument::query()->purchaseRequestsForBacSecretariat($user), 'bac-secretariat.pr.show');
                $this->addProcurementSources($sources, $user, false);
                break;
            case 'budget':
                $addDocument(ProcurementDocument::query()->visibleToBudgetOfficer($user)
                    ->whereIn('status', [ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW]), 'budget.pending-review.show');
                $addDocument(ProcurementDocument::query()->reviewedByBudgetOfficer($user), 'budget.reviewed.show');
                $addDocument(ProcurementDocument::query()->returnedByBudgetOfficer($user), 'budget.returned.show');
                break;
            case 'accounting':
                $addDocument(ProcurementDocument::query()->visibleToAccountingOfficer($user)
                    ->whereIn('status', [ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW, ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW]), 'accounting.pending-review.show');
                $addDocument(ProcurementDocument::query()->reviewedByAccountingOfficer($user), 'accounting.reviewed.show');
                $addDocument(ProcurementDocument::query()->returnedByAccountingOfficer($user), 'accounting.returned.show');
                break;
            case 'bac-member':
                $addDocument(ProcurementDocument::query()->assignedToBacMember($user), 'bac-member.review.show');
                $addDocument(ProcurementDocument::query()->reviewedByBacMember($user), 'bac-member.reviewed.show');
                break;
            case 'bac-chair':
                $addDocument(ProcurementDocument::query()->assignedToBacChair($user), 'bac-chair.approvals.show');
                $addDocument(ProcurementDocument::query()->forBacChairConfirmation($user), 'bac-chair.confirmation.show');
                $addDocument(ProcurementDocument::query()->reviewedByBacChair($user), 'bac-chair.reviewed.show');
                if ($user->hasPermission('bac.resolution.view')) {
                    $sources[] = [BacResolution::query()->whereIn('status', [
                        BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
                        BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
                        BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
                        BacResolution::STATUS_FORWARDED_TO_HOPE,
                        BacResolution::STATUS_APPROVED_BY_HOPE,
                        BacResolution::STATUS_RETURNED_BY_HOPE,
                    ]), ['resolution_number', 'document_reference_number', 'title', 'project_title', 'supplier_name'],
                        'BAC resolution', 'bac-chair.resolutions.show', 'resolution_number', 'project_title', 'status'];
                }
                break;
            case 'approving-authority':
                $addDocument(ProcurementDocument::query()->assignedToApprovingAuthority($user), 'approving-authority.pending.show');
                $addDocument(ProcurementDocument::query()->approvedByApprovingAuthority($user), 'approving-authority.approved.show');
                break;
            case 'pr-numbering':
                $addDocument(ProcurementDocument::query()
                    ->whereIn('document_type', ['PR', 'Purchase Request'])
                    ->where('status', ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)
                    ->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT), 'pr-numbering.pending.show');
                break;
            case 'admin':
                $sources[] = [SvpProcurementChain::query(), ['chain_number', 'tracking_number', 'office_name'],
                    'SVP file', 'admin.svp-chains.show', 'chain_number', 'office_name', 'current_status'];
                break;
        }

        return $sources;
    }

    private function addProcurementSources(array &$sources, User $user, bool $headOffice): void
    {
        $prefix = $headOffice ? 'head-office' : 'bac-secretariat';
        $scope = function (Builder $query) use ($user): Builder {
            return $query->where(function (Builder $office) use ($user) {
                if ($user->office_id) {
                    $office->where('submitting_office_id', $user->office_id)
                        ->orWhere('current_office_id', $user->office_id);
                }
                $office->orWhere('submitted_by_user_id', $user->id)
                    ->orWhere('prepared_by_user_id', $user->id);
            });
        };

        $rfqs = Rfq::query();
        $abstracts = AbstractQuotation::query();
        $orders = PurchaseOrder::query();

        if ($headOffice) {
            $rfqs->where(function (Builder $query) use ($user, $scope) {
                $query->where('prepared_by_user_id', $user->id)->orWhere('submitted_by_user_id', $user->id)
                    ->orWhereHas('sourcePrDocument', $scope)
                    ->orWhereHas('sourceBacResolution.sourcePrDocument', $scope);
            });
            $abstracts->where(function (Builder $query) use ($user, $scope) {
                $query->where('prepared_by_user_id', $user->id)->orWhere('submitted_by_user_id', $user->id)
                    ->orWhereHas('sourcePrDocument', $scope)
                    ->orWhereHas('sourceRfq.sourcePrDocument', $scope)
                    ->orWhereHas('sourceRfq.sourceBacResolution.sourcePrDocument', $scope)
                    ->orWhereHas('sourceBacResolution.sourcePrDocument', $scope);
            });
            $orders->where(function (Builder $query) use ($user, $scope) {
                $query->where('prepared_by_user_id', $user->id)->orWhere('submitted_by_user_id', $user->id)
                    ->orWhereHas('sourcePrDocument', $scope)
                    ->orWhereHas('sourceAbstract.sourcePrDocument', $scope)
                    ->orWhereHas('sourceAbstract.sourceRfq.sourcePrDocument', $scope)
                    ->orWhereHas('sourceBacResolution.sourcePrDocument', $scope);
            });
            $sources[] = [BacResolution::query()->whereHas('sourcePrDocument', function (Builder $query) use ($scope) {
                $scope($query)->whereIn('status', [
                    ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
                    ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
                    ProcurementDocument::STATUS_SVP_POSTING_COMPLETED,
                    ProcurementDocument::STATUS_READY_FOR_RFQ,
                ]);
            }), ['resolution_number', 'document_reference_number', 'title', 'project_title', 'supplier_name'],
                'BAC resolution', 'head-office.resolutions.show', 'resolution_number', 'project_title', 'status'];
        }

        $sources[] = [$rfqs, ['rfq_number', 'document_reference_number', 'supplier_name', 'purpose'],
            'RFQ', $prefix . '.rfqs.show', 'display_number', 'supplier_name', 'status'];
        $sources[] = [$abstracts, ['abstract_number', 'document_reference_number', 'project_name', 'lowest_supplier_name',
            'supplier_1_name', 'supplier_2_name', 'supplier_3_name', 'supplier_4_name', 'supplier_5_name'],
            'Abstract', $prefix . '.abstracts.show', 'display_number', 'project_name', 'status'];

        if ($headOffice || $user->hasPermission('po.view')) {
            $sources[] = [$orders, ['po_number', 'document_reference_number', 'supplier_name'],
                'Purchase order', $prefix . '.purchase-orders.show', 'display_number', 'supplier_name', 'status'];
        }

        if (! $headOffice && $user->hasPermission('bac.resolution.view')) {
            $sources[] = [BacResolution::query(), ['resolution_number', 'document_reference_number', 'title', 'project_title', 'supplier_name'],
                'BAC resolution', 'bac-secretariat.resolutions.show', 'resolution_number', 'project_title', 'status'];
        }
    }
}
