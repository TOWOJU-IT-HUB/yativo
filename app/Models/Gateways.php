<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gateways extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gateway_name',
        'slug',
        'status',
        'payout',
        'deposit',
        'payin_currencies',
        'payout_currencies',
    ];

    protected $casts = [
        'payin_currencies' => 'array',
        'payout_currencies' => 'array',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
