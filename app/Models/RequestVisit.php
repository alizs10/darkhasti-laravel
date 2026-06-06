<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestVisit extends Model
{
    use HasFactory;

    protected $table = 'requests_visits';

    public $timestamps = false; // because you don't have created_at / updated_at

    protected $fillable = [
        'request_id',
        'visited_at',
        'user_id',
        'ip_address',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
