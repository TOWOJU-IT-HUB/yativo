<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessUbo extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        "business_id",
        "ubo_name",
        "ubo_email",
        "ubo_whatsapp",
        "ubo_position",
        "ubo_social",
        "ubo_verification_url",
        "ubo_verification_reference",
        "ubo_verification_status",
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->where('id', $this->business->user_id);
    }

    public function getUboVerificationStatusAttribute($value)
    {
        return $value === 'verified' ? true : false;
    }

    public function getUboVerificationUrlAttribute($value)
    {
        return $value ? $value : null;
    }

    public function getUboVerificationReferenceAttribute($value)
    {
        return $value ? $value : null;
    }
}
