<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'author_id',
        'body',
        'is_chosen_answer',
        'parent_id',
        'request_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_chosen_answer' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'likes_count',
        'dislikes_count',
        'replies_count',
        'user_vote_status', // Add this
    ];

    /**
     * Get the author of the comment.
     */
    public function author(): BelongsTo
    {
        // Links to the 'users' table via 'author_id'
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the request that the comment belongs to.
     */
    public function request(): BelongsTo
    {
        // Links to the 'requests' table via 'request_id'
        return $this->belongsTo(Request::class, 'request_id');
    }

    /**
     * Get the attached files for this comment.
     * This uses the polymorphic relationship defined in the attached_files table.
     */
    public function attachedFiles(): MorphMany
    {
        return $this->morphMany(AttachedFile::class, 'attachable');
    }

    /**
     * Get the parent comment.
     * Null if this is a top-level comment on a request.
     */
    public function parent(): BelongsTo
    {
        // Links to the 'comments' table via 'parent_id' (self-referencing)
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the child comments of this comment (replies).
     */
    public function childs(): HasMany
    {
        // Links to other comments where 'parent_id' is this comment's ID.
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function votes()
    {
        return $this->hasMany(CommentVote::class, 'comment_id');
    }

    public function likes()
    {
        return $this->votes()->whereRaw('vote = 1');
    }

    public function dislikes()
    {
        return $this->votes()->whereRaw('vote = 2');
    }

    public function replies()
    {
        return $this->childs();
    }

    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    public function getLikesCountAttribute(): int
    {
        return $this->likes()->count();
    }

    public function getDislikesCountAttribute(): int
    {
        return $this->dislikes()->count();
    }

    public function ancestors()
    {
        return $this->parent()->with(['ancestors', 'author']);
    }

    // public function scopeWithUserVote($query, $userId)
    // {
    //     return $query->addSelect([
    //         'user_vote_status' => CommentVote::select('vote')
    //             ->whereColumn('comment_id', 'comments.id')
    //             ->where('user_id', $userId)
    //             ->limit(1),
    //     ]);
    // }

    public function getUserVoteStatusAttribute()
    {

        $vote = $this->votes()->firstWhere('user_id', auth('api')->id());

        return $vote ? $vote->vote : null;
    }

    public function deleteWithDescendants(): void
    {
        // Recursively delete all child comments (replies) first
        foreach ($this->childs as $child) {
            $child->deleteWithDescendants();
        }

        // Delete attached files for this comment
        $this->attachedFiles()->delete();

        // Soft delete this comment
        $this->delete();
    }
}
