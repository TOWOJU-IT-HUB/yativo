<?php

namespace App\Http\Controllers;

use App\Models\Track;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $datas = Track::whereTrackId($id)->whereUserId(active_user())->latest()->get();
            return get_success_response($datas);
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage());
        }
    }
}


