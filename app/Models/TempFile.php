<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TempFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_size',
        'mime_type',
        'file_hash',
        'file_path',
        'attachable_id',
        'attachable_type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'disk',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scope for cleaning
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeUnattached($query)
    {
        return $query->whereNull('attachable_id');
    }

    public function getDiskAttribute(): string
    {
        return 'temp';
    }
}
