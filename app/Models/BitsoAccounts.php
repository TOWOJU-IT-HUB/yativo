<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BitsoAccounts extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "customer_id",
        "user_id",
        "account_number",
        "provider_response",
    ];

    protected $hidden = [
        "updated_at",
        "deleted_at"
    ];

    protected $casts = [
        "provider_response" => "array"
    ];
}
