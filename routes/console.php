<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('guest-access:prune', function () {
    $count = \Illuminate\Support\Facades\DB::table('guest_access_requests')->where('expires_at', '<', now()->subDay())->delete();
    $this->info("Removed {$count} expired sign-in requests.");
})->purpose('Remove expired guest sign-in credentials')->daily();
