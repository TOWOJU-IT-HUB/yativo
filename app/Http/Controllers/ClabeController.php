<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ClabeService;

class ClabeController extends Controller
{
    public function generateClabes(ClabeService $clabeService)
    {
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $clabe = $clabeService->generateNextClabe();
            $validation = $clabeService->validate($clabe);
            $results[] = [
                'clabe' => $clabe,
                'status' => $validation['ok'] ? 'Valid' : 'Invalid',
                'details' => $validation,
            ];
        }

        return response()->json($results);
    }
}