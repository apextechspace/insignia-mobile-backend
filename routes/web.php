<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->role === 'ipl_admin' || $user->role === 'admin') {
            return redirect('/admin');
        }
        return redirect('/operations');
    }

    // Default to the Operations portal login for staff, MCL, and team members
    return redirect('/operations/login');
});
