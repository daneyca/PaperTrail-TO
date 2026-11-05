<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitiveBiddingCertificationDraft extends Model
{
    protected $fillable = ['user_id', 'payload', 'revision'];

    protected $casts = ['payload' => 'array', 'revision' => 'integer'];
}
