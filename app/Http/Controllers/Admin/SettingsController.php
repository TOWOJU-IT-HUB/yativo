<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return $settings = \App\Models\settings::all();
        return view('admin.settings.index');
    }
}
