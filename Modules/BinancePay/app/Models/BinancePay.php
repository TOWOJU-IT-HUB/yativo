<?php

namespace Modules\BinancePay\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BinancePay\Database\factories\BinancePayFactory;

class BinancePay extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "deposit_id",
        "gateway_id",
        "trx_type"
    ];
    
    protected static function newFactory(): BinancePayFactory
    {
        //return BinancePayFactory::new();
    }
}
