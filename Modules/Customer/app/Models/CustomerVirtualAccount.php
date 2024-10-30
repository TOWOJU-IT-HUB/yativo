<?php

namespace Modules\Customer\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Database\factories\CustomerVirtualAccountFactory;
use Illuminate\Support\Str;

class CustomerVirtualAccount extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "business_id",
        "customer_id",
        "account_info",
        "account_status",
        "meta_data"
    ];

    protected $hidden = [
        "meta_data"
    ];

    protected $casts = [
        "account_info" => "array",
        "meta_data" => "array"
    ];
    
    // Use UUID as the primary key type
    protected $keyType = 'string';

    // Disable auto-incrementing
    public $incrementing = false;

    // Automatically generate UUID when creating a new model instance
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
    
    protected static function newFactory(): CustomerVirtualAccountFactory
    {
        // return CustomerVirtualAccountFactory::new();
    }
}
