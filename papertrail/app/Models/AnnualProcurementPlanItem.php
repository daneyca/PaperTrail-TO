<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualProcurementPlanItem extends Model
{
    protected $fillable = [
        'annual_procurement_plan_id',
        'app_version',
        'source_ppmp_document_id',
        'source_ppmp_item_id',
        'row_order',
        'category',
        'project_title',
        'end_user_unit',
        'general_description',
        'pap_code',
        'procurement_program_project',
        'pmo_end_user',
        'mode_of_procurement',
        'early_procurement_activity',
        'bid_evaluation_criteria',
        'start_procurement_activity',
        'end_procurement_activity',
        'ads_post_ib_rei',
        'sub_open_bids',
        'notice_of_award',
        'contract_signing',
        'schedule_merged',
        'is_schedule_merged',
        'source_of_funds',
        'estimated_budget',
        'procurement_strategy_or_tools',
        'estimated_total',
        'personal_outlay',
        'mooe',
        'co',
        'remarks',
        'sort_order',
    ];

    protected $casts = [
        'app_version' => 'integer',
        'estimated_budget' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'personal_outlay' => 'decimal:2',
        'mooe' => 'decimal:2',
        'co' => 'decimal:2',
        'is_schedule_merged' => 'boolean',
    ];

    public function annualProcurementPlan(): BelongsTo
    {
        return $this->belongsTo(AnnualProcurementPlan::class);
    }

    public function sourcePpmpDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_ppmp_document_id');
    }
}
