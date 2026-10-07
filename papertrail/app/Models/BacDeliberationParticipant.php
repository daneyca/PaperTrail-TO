<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacDeliberationParticipant extends Model
{
    public const ATTENDANCE_PENDING = 'pending';
    public const ATTENDANCE_PRESENT = 'present';
    public const ATTENDANCE_ABSENT = 'absent';
    public const ATTENDANCE_EXCUSED = 'excused';

    public const RECOMMEND_FOR_BAC_CHAIR_REVIEW = 'recommend_for_bac_chair_review';
    public const RECOMMEND_RETURN = 'recommend_return';
    public const RECOMMEND_FOR_APPROVAL = 'recommend_for_approval';
    public const RECOMMEND_WITH_COMMENTS = 'recommend_with_comments';

    protected $fillable = [
        'bac_deliberation_id',
        'user_id',
        'role_name',
        'attendance_status',
        'recommendation',
        'recommendation_remarks',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function bacDeliberation(): BelongsTo
    {
        return $this->belongsTo(BacDeliberation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
