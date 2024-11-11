<?php

namespace App\Models;

use Creatydev\Plans\Models\PlanModel;
use Illuminate\Database\Eloquent\Model;

class Plan extends PlanModel
{
    protected $casts = [
        "metadata" => "array",
    ];
}
