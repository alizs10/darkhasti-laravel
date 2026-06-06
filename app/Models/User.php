<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Typically the user's ID
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return []; // Add any custom claims here if needed, e.g., roles, permissions
    }

    /**
     * Get the requests associated with the user.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'author_id'); // Assuming 'author_id' is the FK in the requests table
    }

    /**
     * Get the attached files uploaded by the user.
     */
    public function attachedFiles(): HasMany
    {
        return $this->hasMany(AttachedFile::class); // 'user_id' is the FK in attached_files table
    }

    /**
     * Get the attached files uploaded by the user.
     */
    public function tempFiles(): HasMany
    {
        return $this->hasMany(TempFile::class); // 'user_id' is the FK in attached_files table
    }

    /**
     * Get the comments authored by the user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_id'); // Assuming 'author_id' is the FK in the comments table
    }

    public function requestVisits()
    {
        return $this->hasMany(RequestVisit::class);
    }

    public function requestVotes(): HasMany
    {
        return $this->hasMany(RequestVote::class, 'user_id'); // Assuming 'author_id' is the FK in the comments table
    }

    public function commentVotes(): HasMany
    {
        return $this->hasMany(CommentVote::class, 'user_id'); // Assuming 'author_id' is the FK in the comments table
    }
}
