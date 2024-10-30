<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\app\Models\Customer;

class CryptoDeposit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "user_id",
        "currency",
        "amount",
        "address",
        "transaction_id",
        "status",
        "payload",
        "customer_id"
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    protected $hidden = [
        'payload',
        'updated_at',
        'deleted_at'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    public function customer()
    {
        return $this->belongsTo(Customer::class, "customer_id", "customer_id");
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
