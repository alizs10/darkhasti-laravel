<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB; // Import DB facade

class Request extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'published_at',
        'author_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
        'deleted_at' => 'datetime', // For soft deletes
    ];

    protected $appends = [
        'is_answered',
        // 'visits_count',
        'likes_count',
        'dislikes_count',
        // 'comments_count',
    ];

    /**
     * Get the author of the request.
     */
    public function author(): BelongsTo
    {
        // Assumes 'author_id' in this table links to 'id' in the 'users' table.
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the attached files for this request.
     * This uses the polymorphic relationship defined in the attached_files table.
     */
    public function attachedFiles(): MorphMany
    {
        return $this->morphMany(AttachedFile::class, 'attachable');
    }

    public function visits()
    {
        return $this->hasMany(RequestVisit::class);
    }

    public function votes()
    {
        return $this->hasMany(RequestVote::class, 'request_id');
    }

    public function likes()
    {
        return $this->votes()->whereRaw('vote = 1');
    }

    public function dislikes()
    {
        return $this->votes()->whereRaw('vote = 2');
    }

    /**
     * Get the comments for this request.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class); // Assumes 'request_id' FK in comments table
    }

    public function replies()
    {
        return $this->comments()->whereNull('parent_id');
    }

    /**
     * Get the likes count from the requests_votes table.
     * This is a computed property, not a direct Eloquent relationship.
     */
    public function getLikesCountAttribute(): int
    {
        return $this->likes()->count();
    }

    /**
     * Get the dislikes count from the requests_votes table.
     * This is a computed property, not a direct Eloquent relationship.
     */
    public function getDislikesCountAttribute(): int
    {
        return $this->dislikes()->count();
    }

    /**
     * Get the visits count from the requests_visits table.
     * This is a computed property.
     */
    // public function getVisitsCountAttribute(): int
    // {
    //     return $this->visits()->count();
    // }

    /**
     * Check if the request has a chosen answer among its comments.
     * This is a computed property based on the 'is_chosen_answer' field in the comments table.
     */
    public function getIsAnsweredAttribute(): bool
    {
        return $this->comments()->where('is_chosen_answer', true)->exists();
    }

    /**
     * Define a relationship to get the chosen answer comment, if any.
     * This is useful if you specifically want to retrieve the chosen answer.
     */
    public function chosenAnswer(): HasOne
    {
        return $this->hasOne(Comment::class)
            ->where('is_chosen_answer', true)
            ->withDefault(null); // Returns null if no chosen answer exists
    }

    public function deleteWithCommentsAndFiles(): void
    {
        // 1. Delete all top‑level comments (they will recursively delete their replies)
        foreach ($this->comments as $comment) { // replies() returns top‑level comments
            $comment->deleteWithDescendants();
        }

        // 2. Delete attached files of the request itself
        $this->attachedFiles()->delete(); // triggers physical deletion via AttachedFile event

        // 3. Soft‑delete the request
        $this->delete();
    }
}
