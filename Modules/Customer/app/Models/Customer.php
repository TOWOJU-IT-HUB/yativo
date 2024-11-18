<?php

namespace Modules\Customer\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Bitnob\app\Models\VirtualCards;
use Modules\Customer\Database\factories\CustomerFactory;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    // protected $fillable = [
    //     "user_id",
    //     "customer_id",
    //     "customer_name",
    //     "customer_email",
    //     "customer_phone",
    //     "customer_country",
    //     "customer_address",
    //     "customer_idType",
    //     "customer_idNumber",
    //     "customer_idCountry",
    //     "customer_idExpiration",
    //     "customer_idFront",
    //     "customer_idBack",
    //     "can_create_vc", // can_create_virtual_card
    //     "can_create_va", // can_create_virtual_account
    //     "customer_status",
    //     "json_data",
    //     "vc_customer_id",
    //     "customer_kyc_status",
    //     "customer_type",
    // ];

    protected $guarded = [];


    protected $hidden = [
        'id',
        'user_id',
        'customer_idType',
        'customer_idNumber',
        'customer_idCountry',
        'customer_idExpiration',
        'customer_idFront',
        'customer_idBack',
        'json_data',
        'card_user_id',
        "updated_at",
        "deleted_at",
        "vc_customer_id",
        "customer_kyc_status",
        "customer_type",
    ];

    protected $casts = [
        "json_data" => 'array',
        "customer_address" => "array",
        "can_create_vc" => "boolean",
        // "can_create_va" => "boolean",
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Automatically generate UUID when creating a new model instance
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Str::uuid();
            }
        });
    }

    public function virtual_cards(): HasMany
    {
        return $this->hasMany(VirtualCards::class, 'customer_id', 'customer_id');
    }
    
    protected static function newFactory(): CustomerFactory
    {
        //return CustomerFactory::new();
    }
}
