<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Observer: auto-post split-ledger entry when a booking is confirmed/paid
        \App\Models\Booking::observe(\App\Observers\BookingObserver::class);

        // Policies: server-side data isolation gates
        Gate::policy(\App\Models\Expense::class,                 \App\Policies\ExpensePolicy::class);
        Gate::policy(\App\Models\MonthlyStatement::class,        \App\Policies\MonthlyStatementPolicy::class);
        Gate::policy(\App\Models\AccommodationLedger::class,     \App\Policies\AccommodationLedgerPolicy::class);
        Gate::policy(\App\Models\PettyCashTransaction::class,    \App\Policies\PettyCashTransactionPolicy::class);
        Gate::policy(\App\Models\DailyReconciliation::class,     \App\Policies\DailyReconciliationPolicy::class);
        Gate::policy(\App\Models\AssetHandover::class,           \App\Policies\AssetHandoverPolicy::class);
    }
}

