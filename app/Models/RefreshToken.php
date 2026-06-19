<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'revoked',
        'revoked_at',
        'replacement_refresh_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
