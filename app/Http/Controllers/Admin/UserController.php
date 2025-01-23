<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->where('role', 'user')->orderBy('id', 'desc')->get();
        return Inertia::render('Users/Index', compact('users'));
    }
}
