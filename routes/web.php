<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/admin/ipl-statement-print', \App\Http\Controllers\IplStatementPrintController::class)
    ->name('ipl-statement.print');

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
