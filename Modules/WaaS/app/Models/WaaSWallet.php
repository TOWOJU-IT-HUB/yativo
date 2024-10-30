<?php

namespace Modules\WaaS\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Customer\app\Models\Customer;
use Modules\WaaS\Database\factories\WaaSWalletFactory;

class WaaSWallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['user_id', 'customer_id', 'balance', 'currency'];
    
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function user() : HasMany
    {
        return $this->hasMany(User::class);
    }
    
    public function transactions()
    {
        return $this->belongsTo(WaaSTransactions::class);
    }


    protected static function newFactory(): WaaSWalletFactory
    {
        //return WaaSWalletFactory::new();
    }
}
