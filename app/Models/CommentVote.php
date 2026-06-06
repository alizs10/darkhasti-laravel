<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentVote extends Model
{
    use HasFactory;

    protected $table = 'comments_votes';

    protected $fillable = [
        'comment_id',
        'user_id',
        'vote', // 'like' | 'dislike'
    ];

    protected $casts = [
        'vote' => 'string',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
