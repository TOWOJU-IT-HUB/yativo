<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VirtualAccountDeposits extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "payload" => "object",
        "response_body" => "object"
    ];
    
}
