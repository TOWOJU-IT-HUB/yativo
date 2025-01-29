<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomPricing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gateway_id',
        'fixed_charge',
        'float_charge',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gateway()
    {
        return $this->morphTo();
    }
}
