<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VirtualAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "account_id",
        "customer_id",
        "user_id",
        "currency",
        "account_info",
        "request_object",
        "account_number",
        'provider_name',
        'extra_data',
        'status',
    ];

    protected $hidden = [
        'status',
        'extra_data',
        'updated_at',
        'id',
        'provider_name',
        'deleted_at',
        'request_object'
    ];

    protected $casts = [
        "account_info" => "array",
        "request_object" => "array",
        "extra_data" => "array"
    ];
}
