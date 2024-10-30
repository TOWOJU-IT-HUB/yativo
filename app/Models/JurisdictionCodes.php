<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JurisdictionCodes extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "jurisdiction_name",
        "jurisdiction_code"
    ];

    protected $visible = [
        'created_at',
        'updated_at',
        'deleted_at',
        'id'
    ];
}
