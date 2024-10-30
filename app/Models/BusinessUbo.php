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
}
