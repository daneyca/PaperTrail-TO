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







public function create(Request $request): View|RedirectResponse
{
    $office = $this->assignedOffice($request->user());

    if (!$office) {
        return redirect()
            ->route('head-office.dashboard')
            ->with('error', 'Your account does not have an assigned office.
             Please contact the administrator.');
    }

    return view('head-office.ppmp.create', [
        'document' => new ProcurementDocument([
            'fiscal_year' => now()->year,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
        ]),
        'office' => $office,
        'items' => $this->blankRows(),
        'options' => $this->options(),
    ]);
}