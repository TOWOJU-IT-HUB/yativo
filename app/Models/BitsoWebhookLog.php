<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BitsoWebhookLog extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'fid',
        'status',
        'currency',
        'method',
        'method_name',
        'amount',
        'details'
    ];

    protected $casts = [
        'details' => 'array', 
    ];
}
