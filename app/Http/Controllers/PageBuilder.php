<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PageBuilder extends Controller
{
    public function index()
    {
        return Inertia::render('Builder/Index');
    }
}
