<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualProcurementPlanVersion extends Model
{
    protected $fillable = [
        'annual_procurement_plan_id',
        'version_no',
        'app_number',
        'app_no',
        'document_reference_number',
        'fiscal_year',
        'plan_type',
        'update_version_no',
        'title',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'submitted_at',
        'approved_at',
        'returned_at',
        'return_reason',
        'total_epa_budget',
        'total_cse_budget',
        'total_estimated_budget',
        'total_personal_outlay',
        'total_mooe',
        'total_co',
        'document_html',
        'document_text',
        'items_json',
        'signatories_json',
        'metadata',
        'remarks',
        'change_summary',
        'created_by_user_id',
    ];

    protected $casts = [
        'version_no' => 'integer',
        'fiscal_year' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
        'total_epa_budget' => 'decimal:2',
        'total_cse_budget' => 'decimal:2',
        'total_estimated_budget' => 'decimal:2',
        'total_personal_outlay' => 'decimal:2',
        'total_mooe' => 'decimal:2',
        'total_co' => 'decimal:2',
        'items_json' => 'array',
        'signatories_json' => 'array',
        'metadata' => 'array',
    ];

    public function annualProcurementPlan(): BelongsTo
    {
        return $this->belongsTo(AnnualProcurementPlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
