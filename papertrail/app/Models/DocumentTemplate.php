<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'template_name',
        'file_path',
        'file_format',
        'version',
        'fiscal_year',
        'is_active',
        'uploaded_by',
        'notes',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActiveFor(Builder $query, string $documentType): Builder
    {
        return $query
            ->where('document_type', $documentType)
            ->where('is_active', true);
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }
}
