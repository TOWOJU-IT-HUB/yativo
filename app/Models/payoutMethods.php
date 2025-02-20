<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class payoutMethods extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "method_name",
        "gateway",
        "country",
        "currency",
        "payment_mode",
        "charges_type",
        "fixed_charge",
        "float_charge",
        "estimated_delivery",
        "pro_fixed_charge",
        "pro_float_charge",
        "minimum_withdrawal",
        "maximum_withdrawal",
        "minimum_charge",
        "maximum_charge",
        "cutoff_hrs_start",
        "cutoff_hrs_end",
        "base_currency",
        "exchange_rate_float"
    ];

    protected $hidden = [
        "payment_mode",
        "charges_type",
        "fixed_charge",
        "float_charge",
        "estimated_delivery",
        "pro_fixed_charge",
        "pro_float_charge",
        "minimum_withdrawal",
        "maximum_withdrawal",
        "minimum_charge",
        "maximum_charge",
        // "exchange_rate_float",
        "gateway",
        "created_at",
        "updated_at",
        "deleted_at"
    ];
    
}
