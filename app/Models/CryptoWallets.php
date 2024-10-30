<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\app\Models\Customer;

class CryptoWallets extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        "user_id",
        "is_customer",
        "customer_id",
        "wallet_address",
        "wallet_currency",
        "wallet_network",
        "wallet_provider",
        "coin_name",
        "wallet_status",
        "wallet_balance",
        "wallet_status",
        "wallet_payload"
    ];

    protected $casts = [
        "wallet_payload" => "array",
        "customer_id" => "string"
    ];

    protected $hidden = [
        "user_id",
        "wallet_payload",
        "deleted_at",
        "updated_at",
        "updated_at",
        'wallet_provider'
    ];  
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, "customer_id", "customer_id");
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

}
