<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Different from Brla this is for Brazilian virtual account number generation
class BrlVirtualAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
}
