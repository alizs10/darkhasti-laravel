<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestVote extends Model
{
    use HasFactory;

    protected $table = 'requests_votes';

    protected $fillable = [
        'request_id',
        'user_id',
        'vote', // 'like' | 'dislike'
    ];

    protected $casts = [
        'vote' => 'string',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
