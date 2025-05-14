<?php

namespace Modules\Khipu\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Khipu\app\Services\KhipuServices;

class KhipuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('khipu::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('khipu::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('khipu::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('khipu::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    public function webhook(Request $request)
    {
        // Get raw JSON payload (body of the request)
        $jsonPayload = file_get_contents('php://input');

        $khipu = new KhipuServices();
        if($khipu->webhook($jsonPayload)) {
            // process webhook as it's valid
        }
    }
}
