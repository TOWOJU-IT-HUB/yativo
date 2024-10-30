<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Currencies\app\Models\Currency;
use Bavix\Wallet\Models\Wallet as BavixWallet;
 
class Wallet extends BavixWallet
{
    use HasFactory, SoftDeletes;

    protected $table = "e_wallets";

    protected $hidden = [
        "holder_type",
        "holder_id",
        "uuid",
        "created_at",
        "updated_at"
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithCurrencyData($query)
    {
        return $query->with('currencyData');
    }

    public function currencyData()
    {
        return $this->hasOne(Currency::class, 'wallet', 'currency');
    }
}
