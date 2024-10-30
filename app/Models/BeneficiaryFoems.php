<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeneficiaryFoems extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = ["gateway_id", "currency", "form_data"];

    protected $hidden = [
        "deleted_at",
        "updated_at",
        "created_at",
        "id"
    ];

    protected $casts = [
        "form_data" => "array"
    ];
}
