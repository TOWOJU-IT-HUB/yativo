<?php

namespace Modules\Customer\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Customer\Database\factories\DojahVerificationFactory;

class DojahVerification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "user_request",
        "kyc_status",
        "dojah_kyc_url",
        "dojah_response",
        "verification_response",
    ];

    protected $casts = [
        "user_request" => "array",
        "dojah_response" => "array",
        "verification_response" => "array",
    ];
    
    protected static function newFactory(): DojahVerificationFactory
    {
        //return DojahVerificationFactory::new();
    }

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
     protected static function boot()
     {
         parent::boot();

         static::creating(function ($model) {
             $model->id = (string) \Str::uuid();
         });
     }}
