<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        // "coupon_code",
        // "coupon_discount",
        // "coupon_expires_at",
        // "coupon_status",
        // "coupon_type"
        "gateway_id",
        "rate_type",
        "float_percentage",
        "float_amount"
    ];

    protected $hidden = [
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function gateway()
    {
        return $this->belongsTo(Gateways::class);
    }

    public function payin()
    {
        return $this->belongsTo(PayinMethods::class, 'gateway_id');
    }

    public function payout()
    {
        return $this->belongsTo(payoutMethods::class, 'gateway_id');
    }

}
