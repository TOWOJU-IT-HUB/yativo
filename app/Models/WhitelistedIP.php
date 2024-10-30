<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhitelistedIP extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "whitelisted_ips";

    protected $fillable = [
        'ip_address',
        'user_id'
    ];

    protected $hidden = [
        'user_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'id'
    ];

    protected $casts = [
        'ip_address' => 'array'
    ];
}
