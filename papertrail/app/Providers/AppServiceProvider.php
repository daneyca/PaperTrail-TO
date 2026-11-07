<?php

namespace App\Providers;

use App\Models\CompetitiveBiddingSignatureDocument;
use App\Observers\CompetitiveBiddingSignatureDocumentObserver;
use Illuminate\Pagination\Paginator;
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
        CompetitiveBiddingSignatureDocument::observe(CompetitiveBiddingSignatureDocumentObserver::class);
        Paginator::defaultView('vendor.pagination.papertrail');
    }
}
