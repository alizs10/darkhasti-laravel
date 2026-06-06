<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class AttachedFile extends Model
{
    protected static function booted()
    {
        static::deleting(function ($attachedFile) {
            // Delete the physical file from the main disk
            if (Storage::disk('public')->exists($attachedFile->file_path)) {
                Storage::disk('public')->delete($attachedFile->file_path);
            }
        });
    }

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_name',
        'file_size',
        'mime_type',
        'user_id',
        'attachable_id',
        'attachable_type',
        'file_hash',
        'file_path',
    ];

    protected $appends = [
        'disk',
    ];

    /**
     * Get the parent attachable model.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDiskAttribute(): string
    {
        return 'main';
    }
}
