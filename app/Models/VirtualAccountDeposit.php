<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VirtualAccountDeposit extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "payload" => "object",
    ];
    
}
