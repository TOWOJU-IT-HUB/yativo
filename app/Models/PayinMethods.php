<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayinMethods extends Model
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
        "settlement_time",
        "pro_fixed_charge",
        "pro_float_charge",
        "minimum_deposit",
        "maximum_deposit",
        "minimum_charge",
        "maximum_charge",
        "cutoff_hrs_start",
        "cutoff_hrs_end",
        "Working_hours_start",
        "Working_hours_end",
    ];

    protected $hidden = [
        "payment_mode",
        "charges_type",
        "fixed_charge",
        "float_charge",
        "settlement_time",
        "pro_fixed_charge",
        "pro_float_charge",
        "minimum_deposit",
        "maximum_deposit",
        "minimum_charge",
        "maximum_charge",
        "cutoff_hrs_start",
        "cutoff_hrs_end",
        "gateway",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    protected $casts = [
        "required_extra_data" => "array"
    ];
    
    public function currency_lists() {
        return $this->hasMany(PayinMethods::class, 'country', 'iso3');
    }
}
