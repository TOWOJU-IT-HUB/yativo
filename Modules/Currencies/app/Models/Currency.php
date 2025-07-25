<?php

namespace Modules\Currencies\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Currencies\Database\factories\CurrencyFactory;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "wallet",
        "main_balance",
        "ledger_balance",
        "currency_icon",
        "currency_name",
        "balance_type",
        "currency_full_name",
        "logo_url",
        "is_true",
        "is_active",
        "can_hold_balance"
    ];

    protected $casts = [
        "is_active"=> "boolean"
    ];
    
    protected static function newFactory()
    {
        //return CurrencyFactory::new();
    }
    
}
