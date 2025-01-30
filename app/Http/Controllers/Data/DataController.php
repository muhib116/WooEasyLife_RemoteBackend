<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DataController extends Controller
{
    public function getTutorials()
    {
        $tutorials = file_get_contents(__DIR__ . '/tutorial.json');

        $decoded_data = json_decode($tutorials);

        return $this->successResponse($decoded_data);
    }
}
